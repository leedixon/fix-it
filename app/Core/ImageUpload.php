<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Takes a file a stranger uploaded and turns it into something safe to serve.
 *
 * Everything here is re-encoded through GD rather than stored as it arrived,
 * and that single decision does most of the work:
 *
 *  - It strips EXIF, which is the part that matters most. A tradesperson
 *    photographs a job on their phone and the file carries GPS coordinates
 *    of a customer's house. Store the original and you have published a
 *    homeowner's address without either of them knowing.
 *  - It guarantees the bytes on disk are an image. A file can be a valid
 *    JPEG and a valid PHP script at once; re-encoding produces new bytes
 *    from the pixels, so whatever was hiding in the original is gone.
 *  - It normalises the format, so the gallery does not have to cope with
 *    progressive JPEGs, CMYK, 16-bit PNGs and animated GIFs.
 *
 * The host allows 1GB uploads, which is not a licence to accept them. The
 * limits below are this application's, set where a phone photo fits and a
 * problem does not.
 */
final class ImageUpload
{
    /** Bytes. A phone photo is 2–6MB; nothing legitimate here is bigger. */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Pixels, checked BEFORE the image is decoded.
     *
     * This is the limit that actually protects the server. A 3MB file can
     * describe a 30000x30000 image, and GD allocates four bytes per pixel
     * the moment it opens it — 3.6GB of memory from a file that looked
     * small. getimagesize reads the header only, so the dimensions are
     * known before anything is allocated.
     */
    public const MAX_PIXELS = 50_000_000;

    /** What a work photo is stored at. Bigger than any card or gallery needs. */
    public const PHOTO_EDGE = 1600;

    /** A logo is shown small and square; 512 is generous for a 96px tile. */
    public const LOGO_EDGE = 512;

    /**
     * @param array<string,mixed> $file one entry from $_FILES
     * @return array{ok:bool,error:string,path:string,width:int,height:int,bytes:int}
     */
    public static function store(array $file, string $dir, string $prefix, bool $isLogo = false): array
    {
        $fail = static fn (string $why): array => [
            'ok' => false, 'error' => $why, 'path' => '', 'width' => 0, 'height' => 0, 'bytes' => 0,
        ];

        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK) {
            return $fail(match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too big.',
                UPLOAD_ERR_PARTIAL   => 'The upload was cut off. Try again.',
                UPLOAD_ERR_NO_FILE   => 'No file was chosen.',
                default              => 'That file could not be read.',
            });
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        // The one check that says this came through an upload rather than
        // being a path somebody put in the form.
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $fail('That file could not be read.');
        }

        $bytes = (int) ($file['size'] ?? 0);
        if ($bytes > self::MAX_BYTES) {
            return $fail('That file is too big — ' . (int) (self::MAX_BYTES / 1024 / 1024) . 'MB at most.');
        }

        // Header only. Nothing is decoded and nothing is allocated yet.
        $info = @getimagesize($tmp);
        if ($info === false) {
            return $fail('That does not look like an image.');
        }

        [$width, $height, $type] = $info;
        if ($width * $height > self::MAX_PIXELS) {
            return $fail('That image is too large to process. Around 50 megapixels is the limit.');
        }
        if ($width < 200 || $height < 200) {
            return $fail('That image is too small to show — 200 pixels at least, on both sides.');
        }

        // The type comes from the file's own header, never its name. A file
        // called photo.jpg is not a JPEG because it is called photo.jpg.
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default        => false,
        };
        if (!$source instanceof \GdImage) {
            return $fail('That image format is not supported. JPEG, PNG or WebP.');
        }

        try {
            $source = self::applyOrientation($source, $tmp, $type);
            $edge   = $isLogo ? self::LOGO_EDGE : self::PHOTO_EDGE;
            $out    = self::resize($source, $edge);

            // PNG for a logo, because a logo without its transparency is a
            // logo in a white box. JPEG for a photo, which has none to keep
            // and is a third of the size without it.
            $name = $prefix . '-' . bin2hex(random_bytes(8)) . ($isLogo ? '.png' : '.jpg');
            $path = rtrim($dir, '/') . '/' . $name;

            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return $fail('The upload folder could not be written to.');
            }

            $wrote = $isLogo
                ? imagepng($out, $path, 6)
                : imagejpeg($out, $path, 82);

            if (!$wrote) {
                return $fail('That image could not be saved.');
            }

            return [
                'ok'     => true,
                'error'  => '',
                'path'   => $name,
                'width'  => imagesx($out),
                'height' => imagesy($out),
                'bytes'  => (int) @filesize($path),
            ];
        } finally {
            // GD images are not garbage collected promptly and these are
            // megabytes each. Freed here so a batch upload cannot stack them.
            if (isset($out) && $out instanceof \GdImage && $out !== $source) {
                imagedestroy($out);
            }
            if ($source instanceof \GdImage) {
                imagedestroy($source);
            }
        }
    }

    /**
     * Turns the picture the right way up.
     *
     * A phone almost never rotates the pixels — it writes "this is sideways"
     * into EXIF and lets the viewer deal with it. Re-encoding throws that tag
     * away, which is the point, so the rotation has to be baked into the
     * pixels first or every photo taken in portrait arrives on its side.
     */
    private static function applyOrientation(\GdImage $img, string $file, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($file);
        $tag  = (int) ($exif['Orientation'] ?? 0);

        $rotated = match ($tag) {
            3       => imagerotate($img, 180, 0),
            6       => imagerotate($img, -90, 0),
            8       => imagerotate($img, 90, 0),
            default => null,
        };

        if ($rotated instanceof \GdImage) {
            imagedestroy($img);
            return $rotated;
        }

        return $img;
    }

    /** Scales to fit inside $edge, never up, keeping the aspect ratio. */
    private static function resize(\GdImage $src, int $edge): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= $edge && $h <= $edge) {
            return $src;
        }

        $scale = min($edge / $w, $edge / $h);
        $nw    = max(1, (int) round($w * $scale));
        $nh    = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        // Without these a PNG's transparency becomes black, which is how a
        // logo ends up as a dark rectangle.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }
}
