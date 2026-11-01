# Member Portal directory

**Status:** proposal, for the design decision in #10869. The code is draft PR #10860, on top of the member sign-in work (#10660). It is not part of the first member sign-in release. It waits on the decisions below and on #10524 (protected households).

## Summary

- Members who sign in to the Member Portal can look up other members: names, photos, addresses, phones, emails, birthdays and anniversaries.
- The church decides three things:
  - whether there is a directory at all;
  - who is listed and who may look;
  - which details it shows.
- It shows the same details the printed directory (Reports → Directory) can print, and orders families the same way.
- **What is missing is the individual's say.** Neither directory lets a person leave themselves out today. This document asks whether they should, and who decides.

## 1. What is built (PR #10860)

**Switch and permission**
- `bPortalShowDirectory`, off by default, turns the directory on.
- It works through the portal permissions model (#10662): the capability `portal.directory.view` comes from a rule, "Directory classifications", which gives it to everyone in the *viewer* classifications (Member by default).
- With the switch off, the capability doesn't exist, and the page and API answer 404.

**Who is listed**
- People in the *listed* classifications (Member by default) who are living and active, not awaiting review, and not in a deactivated family. These are the same exclusions the sign-in rule uses.
- **One entry per family** with two or more listed members: the family name with the head's and spouse's first names, the family photo, address, home phone and anniversary, then each listed member with their own birthday, phones and emails.
- **One entry per other listed person.**
- Members are ordered as the printed directory orders them: heads, then spouses, then children (`sDirRoleHead`, `sDirRoleSpouse`, `sDirRoleChild`), then oldest first.

**What it shows**
- **Details:** each one switches on or off, with the printed directory's defaults: primary address, mailing address if different, home phone, personal phone, work phone, cell phone, email, work email, photo.
- **Turned-off details:** a detail that is off is never read into an entry, so neither the page nor the API can return it.
- **Birthdays and anniversaries:** with the year, without the year, or hidden. A person's existing *Hide age* setting drops the year from their birthday.
- **Photos:** served only for people and families the directory shows (`/api/portal/directory/{person|family}/{id}/photo`); anyone else, or no photo, is the same 404.

**Browse or search only**
- *Browse* lists everyone, A–Z, with a search box.
- *Search only* answers nothing until two characters are typed, and then at most 20 entries, so the whole list never leaves the server.

**Admin**
- Admin → Member Portal → **Directory** tab, saving as you go: listed classifications (with counts), viewer classifications, the details, the date modes, and browse or search only.

**Specs:** API 19, admin UI 28, portal UI 6. See the PR.

## 2. How it protects people today

| Protection | Today |
|---|---|
| The church opts in | Off by default; nothing is listed until an administrator turns it on |
| Who is listed | Chosen classifications only; deceased, inactive, awaiting review and deactivated families never |
| Who may look | Signed-in members in chosen classifications, plus anyone an administrator gives the permission to |
| What is shown | Each detail on or off; dates with or without the year, or hidden; *Hide age* honoured |
| Bulk copying | *Search only* mode returns at most 20 entries per query |
| Protected households (#10524) | **Not applied.** `PortalDirectoryService::withRecordPolicy()` is a placeholder until #10524 merges (design §14 of #10660 requires it) |
| The person's own choice | **None**, and the printed directory has none either |

## 3. Decisions needed

### Q1. Should a person be able to leave themselves out?

- **Today:** a church that prints a directory lists everyone in the classifications it picks. A person who doesn't want their address or phone in it has no way to say so, except asking the office to change their classification, which also changes other things.
- **The portal directory raises the stakes:** it is online and always current, and anyone the church lets sign in can read it.
- **The question:** should a person be able to opt out, of the portal directory *and* the printed directory alike?

### Q2. Should the church decide whether people may opt out?

- Some churches want a complete directory and handle exceptions in the office. Others want each member to decide.
- **Options:**
  - **A.** Opting out is always possible.
  - **B.** A church setting decides whether members may opt out themselves. Staff can always set it for a person, either way.
  - **C.** Only staff can set it, never the member.

### Q3. One place for both directories

- **Requirement:** whatever the answer to Q1 and Q2, the choice lives in **one place** and applies to **both** the printed directory and the portal directory. Nobody should have to opt out twice, or wonder which directory a setting covers.
- **Where each directory applies it:** the printed directory, beside its classification, deceased, awaiting-review and inactive-family filters (`src/Reports/DirectoryReport.php` today, `DirectoryReportService` once #10981 merges); the portal directory, in `PortalDirectoryService`, which mirrors those filters.
- **Where it could live:**
  - **For staff:** a *Directory* row on the person's page.
  - **For the member** (if Q2 allows it): a *Directory* setting on their portal profile (Profile → Update your details), next to Language.
- **Granularity options:**
  - **(a)** one choice: *Listed* / *Not listed*;
  - **(b)** per detail: leave out my phone, my email, my address;
  - **(c)** opt in instead of opt out, for churches that want consent first.
- **The family question:** a family entry shows the household's address and home phone. If one adult opts out, does the family entry drop those, or does it take every adult opting out? With *every adult*, one adult's opt-out is undone by another adult doing nothing; with *any adult*, the names stay listed and only the address and home phone drop. A household-level choice may be simpler to understand than a person-level one. #10869 records the answer.

### Q4. Protected households (#10524)

The directory must apply #10524's record policy before it ships. The question is whether it ships only after #10524 merges, or behind the switch with a release note.

## 4. A starting proposal (to accept, change or reject)

1. **The choice:** one per-person setting, *Show me in the church directory*, stored on the person: granularity (a), *Listed* / *Not listed*. Per detail (b) multiplies entry shapes and admin controls for a first version; opt in (c) would empty every existing printed directory on upgrade. Default: shown, which matches today's printed directory. Both directories honour it. Honouring it in the printed directory changes what a church prints even if it never turns the portal on, so it ships with a release note and a docs update.
2. **Staff:** can always change it on the person's page.
3. **Members:** a church setting, *Let members choose whether they are listed*, shows the same choice on their portal profile. Off by default (option B).
4. **Households:** a family entry leaves out the household address and home phone when every adult in it has opted out; otherwise it shows them.
5. **Shipping:** the directory does not ship until #10524's protected-households policy is applied.

## 5. Not in scope

- A directory for people who can't sign in (the printed directory serves them).
- Messaging other members from the directory.
- Per-viewer rules, such as small group leaders seeing more.

## 6. Data and settings

- `bPortalShowDirectory` (bool, off).
- `aPortalDirectory` (JSON): listed and viewer classification ids, the details, the two date modes, and browse.
- No new tables. A per-person opt-out (Q1–Q3) would add one column or a person setting, plus the church setting from Q2.
