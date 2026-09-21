# The team, and who can do what

Three staff roles. The line that matters is between the first and the other
two: **only a superadmin sees or moves money, changes the team, or deletes an
account.**

| | Superadmin | Manager | Moderator |
| --- | :---: | :---: | :---: |
| Approve and reject applications | ● | ● | ● |
| Suspend and restore listings | ● | ● | ● |
| Remove jobs from the board | ● | ● | ● |
| Activity log | ● | ● | ● |
| People list | ● | ● | |
| Licensing rules | ● | ● | |
| Who is paying for placement | ● | ● | |
| **Revenue and prices** | ● | | |
| **Change prices and slot caps** | ● | | |
| **Add, change and remove staff** | ● | | |
| **Delete an account** | ● | | |
| Markets | ● | | |
| Maintenance mode | ● | | |

A Manager runs the site. A Moderator works the queue. Neither sees a penny.

## Adding somebody

**Admin → Team → Add somebody.** Name, email, role, send.

They get an email with a link to set their own password. **Nobody here ever
knows it** — there is no temporary password to be reused on another site,
forwarded, or left in a message thread. The link works once and expires in
**seven days**; resending is one click and kills the old link at the same time.

Seven rather than the fortnight a tradesperson's invite gets, because this one
is a key to the admin panel and an invite sitting in a forwarded email is live
for exactly as long as that window says.

If the address already has a Fix Listed account — a tradesperson who is also
going to help run things — it is upgraded instead, and **they keep the password
they already have**. Wiping it to force a set-password link would lock them out
of the account they have been using.

## What the screens hide

A Manager and a Moderator do not see a navigation item for anything they
cannot use. Typing the URL gets a **404**, not "access denied": they have
either guessed it or been sent it, and neither deserves confirmation that the
page exists.

Money is **removed from the response**, not hidden with CSS. A Manager's
dashboard does not contain the revenue figure at all — `display:none` on a
number is still that number sitting in the HTML for anyone who opens the
inspector. Where a financial tile would be, they get an operational one: the
waiting list instead of revenue, slots left instead of what the inventory
earns.

## The rails, and why each exists

Every one of these prevents a mistake with no way back through the interface.

- **Nobody acts on their own account.** No self-demotion, no self-suspension,
  no self-deletion. One click to make, an SSH session to undo.
- **The last superadmin who can sign in cannot be removed, demoted or
  suspended** — by anyone, including themselves. A platform with no owner who
  can sign in is a platform nobody can administer. "Can sign in" means active
  *and* has actually set a password; an invited-but-never-accepted superadmin
  does not count, because they cannot get in either.
- **Removal asks you to type their email.** A confirm dialog is clicked
  through by reflex. This one cannot be.
- **Suspending kills any live invite.** Otherwise the account is switched off
  and the link in their inbox is still the way back in.

## Removing somebody

Marked deleted, not erased. Their audit trail points at that row — who
approved which application, who removed which job — and "who let this listing
through" is a question that outlives the person who answered it. A real
`DELETE` would either cascade that history away or leave it pointing at
nothing.

What the removal does do: clears the password, drops the role to `homeowner`,
blanks the name and phone, and releases the email by rewriting it to
`deleted+<id>@fixlisted.invalid` so the real address can be used again. Any
outstanding invite dies with it.

Removing a **tradesperson** from the People screen also suspends their
listing. A live profile behind an account nobody can sign into means a
homeowner quoting into silence.

## Getting back in

`php bin/admin.php` creates or resets a superadmin from the command line. It
is the answer to the one situation the web interface deliberately cannot
handle: no superadmin left who can sign in. It is not the way to add a
Manager or a Moderator — those go through the Team screen so they set their
own password.

## Adding a capability

`app/Core/Auth.php`, the `CAPABILITIES` table. One line, listing the roles
that have it. Then `guardCan('your.capability')` in the controller and
`$can('your.capability')` in the template — the same question in both places,
so a link can never appear to somebody the screen would then refuse.

An unknown capability is **false**, never true. A typo hides a button rather
than exposing one, because the unsafe failure is the silent one.

`bin/smoke.php` holds the whole table as a test, one row per capability. Widen
one by accident and it fails by name.
