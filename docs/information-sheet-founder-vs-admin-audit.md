# Information Sheet — Founder vs Admin Audit

_Audited 2026-09-25. Compares the founder page (`startup/information-sheet/edit.blade.php`) with the admin page (`admin/information-sheets/show.blade.php`), their form requests and their controllers._

## Summary

The two sides are **mostly in sync already**. The main-sheet validation rules are identical for all 50 shared fields, the table-row rules (Core Team, Incubation, L&D, References) are literally shared (the admin request classes extend the founder ones), and the client-side validation script is the same code on both pages.

The differences fall into three groups:

- **A. Intentional:** admin-only fields and founder-only workflow. Keep these.
- **B. Unintended drift:** small differences that should be made the same.
- **C. Gaps found while auditing:** not founder-vs-admin differences, but worth fixing.

## A. Intentional differences (keep)

| # | Area | Founder | Admin | Why it's fine |
|---|---|---|---|---|
| A1 | Admin-only fields | — | Cohort No., Endorsed By, Endorsement Date, Director Approval Date, Portfolio Manager, Business Description, Problem/Solution/Target Market | Reviewer/office fields the founder shouldn't fill |
| A2 | Save modes | **Save** (draft, loose rules) and **Submit for Review** (strict) | Always strict | Founder can save a half-done sheet; admin edits are corrections |
| A3 | Client-side check | Runs only on Submit | Runs on every Save | Follows A2 |
| A4 | Locks | Blocked when profile incomplete, sheet Approved, or on evaluation day | Admin can always edit (e.g. fix a typo after approval) | Documented in the controller |
| A5 | Submit side effects | Sets status Pending, stamps submission/accomplished date, clears stale evaluation bookings after a rejection | Only stamps date accomplished if it was never set | Admin edits must not re-date the founder's declaration |
| A6 | Approve / Reject | — | Admin only | Workflow |
| A7 | Files upload | Founder only | — | Workflow |
| A8 | Error message voice | "Please enter **your** surname." | "Please enter **the** surname." | Same meaning; wording matches who is typing |

## B. Unintended drift (recommend making the same)

| # | Area | Founder | Admin | Recommendation |
|---|---|---|---|---|
| B1 | Core Team "None listed yet." | Hidden whenever the page is in edit mode | Hidden only once a new entry row is added (fixed today) | Use the admin behaviour on both: show it until a row is actually added |
| B2 | Table placeholders | Real examples: `Dela Cruz, Juan, Santos, Jr.`, `09171234567`, `juan.delacruz@gmail.com`, `e.g. PUP-TBIDO`, `e.g. 120`… | Generic labels: `Name`, `Phone`, `Email`, `Hours`… | Use the founder's examples on both. They show the required format (e.g. Surname, Firstname). |
| B3 | Educational Background placeholders | From the row config | `School name`, `Degree or course`, `Highest level / units earned`, `e.g. 2018` | Same as B2 |
| B4 | Civil Status dropdown placeholder | `SELECT CIVIL STATUS` | `Select civil status` | Pick one casing |
| B5 | 5 validation messages say different things | `date_of_birth.before`: "Please enter a valid date of birth." | "Date of birth must be 2009 or earlier." | Use the admin one: it tells the user the actual rule |
| | | `sex.required`: "Please select your sex." | "Choose Male or Female." | Keep the voice difference (A8), align the wording |
| | | `civil_status.required`: "Please select your civil status." | "Choose a civil status." | Same |
| | | `date_of_birth.required`: "Please enter your date of birth." | "Select the date of birth." | Same |
| | | `startup_overview.min`: "Please describe the startup in at least 50 characters." | "The startup overview must be at least 50 characters." | Same |
| B6 | Blank Height / Weight | Blank box → saved as empty (null) | Blank box → passed through as `''` | Use the founder's handling on both (null) |
| B7 | Profile sync | Submitting copies Business Description, mobile no. and residential address into the Startup Profile | Admin corrections are **not** copied | Decide: should an admin correction also update the Startup Profile? If yes, add the same sync to the admin update. |
| B8 | Edit History | Founder saves (main sheet and table rows) are **not** logged | Every admin change is logged | Decide whether Edit History should also show founder changes. If it's meant as an admin-only audit trail, leave as is. |
| B9 | Code duplication | `UpdateInformationSheetRequest` is a full 800-line copy on each side; the two pages are ~2,600-line near-copies | — | Long-term: move the shared rules/messages into one base class and the shared sections into Blade partials so they can't drift again |

## C. Gaps found while auditing

| # | Gap | Where | Recommendation |
|---|---|---|---|
| C1 | **Founder can still change Incubation, L&D and Reference rows on a locked sheet.** The approval lock and evaluation-day lock are only checked on the main sheet and on Core Team rows, not on these three tables' add/edit/remove endpoints. The page hides the buttons, but a direct request would go through. | `Startup\InformationSheetController` store/update/destroy for incubation, ld, references | Add the same lock check used for Core Team (`abortIfInformationSheetLocked`) |
| C2 | "At least one Core Team entry" is enforced only in the browser | Both sides' destroy endpoints | Add a server-side check so the last Core Team row can't be deleted |
| C3 | Unused parameter | Founder `validateInfoSheetForms(root, remaining)`: `remaining` is never used | Remove it |

## Already the same (checked)

- Main-sheet validation rules: all 50 shared fields identical, plus the same N/A handling, education-background consistency check and "a filled field can't be blanked" guard.
- Table-row rules and messages: shared (admin classes extend the founder ones).
- Input types, max lengths, name/phone input guards, Enter-key blocking: identical on both pages.
- Client-side validation (`validateInfoSheetForms`) and the all-or-nothing save (`submitInfoSheetForms`): same logic.
- Row add/remove/discard helpers (`addRow`, `canRemoveRow`, `toggleRemoval`, `autoGrow`…): identical.
- Empty states: required tables say "None listed yet.", optional tables say "N/A" on both.
