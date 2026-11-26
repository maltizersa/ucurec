# UCUREC Ethics Review Tracking System: User guide

The system keeps each application, its documents, reviewers, revisions and the official decision together.
It supports the Committee's existing procedures and forms; it does not decide ethical acceptability.

Sign in at the address given by the REC Secretariat. You are signed out after 30 minutes without activity.
Forgot your password? Ask the IT administrator to reset it; a temporary password is emailed to you, and you must
change it at the next sign-in. Staff accounts made by the IT administrator receive their first password the same way.

## Status of an application

| Status | Meaning | Who acts next |
|---|---|---|
| Draft | Being prepared; not yet sent | Researcher |
| Submitted | Sent to UCUREC; has a reference number | Secretariat |
| For Screening | Secretariat is checking completeness | Secretariat |
| Incomplete | Something is missing; see the list on the application | Researcher |
| For Review | Complete; reviewers are being assigned or are reviewing | Secretariat, Chair, reviewers |
| Awaiting REC Decision | Reviews are in; the Committee decides | Secretariat, Chair |
| For Revision | UCUREC asked for revisions; see the letter and comments | Researcher |
| Decision Released | The final decision and letter are available | — |

---

## Researcher

1. **Sign up** (first time): choose *Sign up* on the sign-in page. You can sign in once the Secretariat activates
   your account.
2. **New application**: enter the short record (title, principal investigator and contact, adviser, college or
   program, research type). Details go in the official UCUREC application form you upload.
3. **Upload documents**: choose the document type and the file (PDF, DOC or DOCX, up to 20 MB each). The
   application form and protocol are required. You may remove a file while it is a draft.
4. **Submit**: tick the confirmation and click *Submit to UCUREC*. You receive a reference number
   (e.g. UCUREC-2026-0001). Submitted files can no longer be changed.
5. **If Incomplete**: the application shows exactly what is missing. Upload the missing files and submit again.
   It becomes a new version of the same application.
6. **If For Revision**: read *Comments from UCUREC* and download the signed letter. Upload the revised files **and**
   a *Response to REC comments*, then submit the revised version.
7. **Decision Released**: download the signed letter or clearance from the application page. For an approval, the
   approved files are the version named in the decision.

Your dashboard lists what needs your action. You see only your own applications.

## REC Secretariat

**Accounts**: *Researcher accounts* lists sign-ups waiting for activation. Activate or deactivate researchers there.

**Screening**
1. Dashboard → an application marked *Screen* → **Start screening**.
2. Open each file and mark every checklist item *Present*, *Missing* or *Not applicable*, with notes for the
   researcher if needed. Record the review route if already decided.
3. **Save screening result**. Any *Missing* item returns the application as *Incomplete* with the list; otherwise it
   becomes *For Review*. Completeness is not approval.

**Reviewers** (on the application page, *Reviewers* panel)
1. **Propose** a reviewer and a due date. The Chairperson must authorize it.
2. Follow progress: *Awaiting reviewer response*, *In review*, *Review submitted*, *Conflict declared*, *Declined*.
   Overdue reviews are flagged red on the dashboard and in the list (filter *Overdue review*).
3. **Cancel** an open assignment with a reason if needed, and propose someone else.
4. When all assignments are closed and at least one review is in, click **Forward for REC decision**.

**Decision**
1. On an application *Awaiting REC Decision*, click **Record REC decision**.
2. Enter the Committee's decision, decision date, approval date and expiry (for approvals), a brief internal basis,
   and the consolidated comments authorized for release (for revisions). Do not include reviewer names.
3. Upload the **signed official letter or clearance**; for full committee review also the decision record or
   minutes extract (stays internal).
4. **Send to Chairperson for confirmation**. If the Chair returns it, the note appears on the decision and on your
   dashboard.

**Revised versions**: when a researcher resubmits a revision, the application page offers **Screen again**,
**Send for further review** or **Send for REC decision**, as UCUREC requires.

**Dashboard charts**: pie charts show, per college / program, the researcher accounts, the submitted applications,
and the applications that passed (released Approved or Exempted decisions). Hover a slice for its count; the list
under each chart gives every count and percentage. The Chairperson sees the same charts.

**Records**: *Applications* has search and filters (status, college, research type, review route, dates, overdue)
and **Download CSV register**. **Print record** on an application gives a paper copy.

## REC Chairperson

- **Reviewer proposals**: dashboard → *Authorize reviewer: …* → on the *Reviewers* panel click **Authorize**, or
  **Not authorize** with a reason.
- **Decisions**: dashboard → *Confirm decision: …* → check the details, files and reviewer recommendations → click
  **Confirm and release** (the researcher then sees the decision, letter and comments), or **Return to Secretariat**
  with a note.
- You can view all submitted applications and download the CSV register.

## Reviewer

1. Dashboard or *My reviews* → open an invitation.
2. **Declare any conflict of interest**, then **Accept** or **Decline**. If you declare a conflict, the assignment
   closes and you will not see the files.
3. After accepting, download the assigned documents (only the version assigned to you). Use them only for this review.
4. Upload your completed **UCUREC Form 4A and/or 4B** (Word or PDF), choose your **recommendation**, add brief comments
   for the Committee, tick the confirmation and **Submit review**. A submitted review cannot be changed.

Your recommendation is advice to the Committee; the Committee makes the decision. Researchers never see reviewer names.

## IT administrator

- **User accounts**: create staff accounts, edit, activate or deactivate, and reset passwords. The temporary
  password is **emailed** to the user (if email is not set up or fails, it is shown to you once instead; give it
  privately). Only the main administrator can manage other administrator accounts.
- **Audit log**: who did what and when (no document contents).
- **Dashboard**: time of the last backup. Backups, restores and deployment are done on the server; see
  `docs/DEPLOYMENT.md`.
- The IT administrator has no access to applications, review reports or decision files.
