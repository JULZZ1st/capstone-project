# Current Workflows

This guide describes the workflows that are currently implemented in the app.

## Authentication

### Who can use it

Anyone with an account can use the login page. To enter the main system, the account must be active and have one of these roles:

- Administrator
- Secretary
- Treasurer

### Sign in

1. Open the login page.
2. Enter the account email address.
3. Enter the password.
4. Optionally select “Remember me on this device”.
5. Select **Sign In**.

If the details are correct and the account is active, the app starts a session and opens the dashboard (or returns to the page the user originally tried to open).

The email is trimmed and treated as lowercase. The app does not tell the user whether the email or password was wrong; it shows a general credentials error instead.

### Sign out

1. Select **Logout** from the sidebar.

The app ends the session, invalidates the session data, creates a new security token, and returns to the login page.

### Important rules

- Login attempts are rate-limited after repeated failures.
- Inactive accounts cannot sign in.
- Users who are required to change their password are sent to the password-change page before using the main system.
- The main operational pages are protected by login, password-change, and role checks.
- Administrators can open the System Administration area. Secretary and Treasurer accounts do not have access to that admin-only area.

## Donor Management

### Who can use it

Administrators, Secretaries, and Treasurers can use Donor Management. They can view the donor list, add donor profiles, open donor history, edit profiles, and remove profiles.

### Find donors

1. Open **Donor Management** from the sidebar.
2. Use the search box to search donor information.
3. Use the donor type filter to show all donors, individuals, or organizations.
4. Review the results in the donor table.

The table shows the donor name, type, contact details, address information, status, and available actions.

### Add a donor

1. Select **Add Donor Profile**.
2. Choose **Individual** or **Organization**.
3. Enter the required name information for the selected type.
4. Enter the required contact number and address.
5. Add optional email, preferred contact method, barangay, city or municipality, province, and notes when available.
6. Select **Save Donor Profile**.

After a valid save, the new donor is added to the list with the default donor status and a success message. If the app detects a likely duplicate based on the donor identity and matching contact details, it shows a warning. The user can review the details and submit again only when it is meant to be a separate profile.

### View donor history

1. Select **View** beside a donor.

The donor history page shows the profile information and the donor’s donation history. Use **Back to Donor Management** to return to the list.

### Edit a donor

1. Select **Edit** beside a donor, or select **Edit Profile** from the donor history page.
2. Update the profile details.
3. Save the changes.

After a valid save, the profile is updated and the app returns to the donor list with a success message.

### Remove a donor

1. Select **Remove** beside a donor, or from the donor history page.
2. Confirm the removal when prompted.

If the donor has no donation history, the profile is deleted. If the donor already has donation history, the profile is kept and changed to inactive instead. This protects the existing donation records. The removal or deactivation is recorded in the security activity log.

### Important rules

- Donor type controls which name fields are required.
- Contact number and address are required when creating or updating a profile.
- Email, preferred contact method, location details, and notes are optional.
- The app checks for likely duplicate donor profiles before creating a new one.
- Removing a donor with donation history does not delete the donor’s history.

## Fund Disbursements

### Who can use it

Administrators, Secretaries, and Treasurers can open Fund Disbursements, submit requests, and view request details.

Only an Administrator can approve, reject, or void a disbursement. The Administrator must be different from the person who submitted the request.

### Create a request

1. Open **Fund Disbursements** from the sidebar.
2. In **Create Request**, enter the amount.
3. Enter the recipient or payee.
4. Select the requested date.
5. Enter the purpose.
6. Optionally enter a supporting reference, such as a Purchase Request No., Receipt No., Check No., or other document reference.
7. Optionally enter notes or remarks.
8. Select **Submit Request**.

After a valid submission:

- The request is saved with the existing `pending` workflow status, shown in the page as **Pending Approval**.
- The request appears in Request History.
- The requester is recorded.
- The request action is written to the security activity log.

### View request history and details

1. Find the request in the Request History table.
2. Select **View**.

The table shows the date, recipient or payee, purpose, amount, requester, status, and action. The details page shows the request fields, additional information, submitted time, and any approval, rejection, or void information.

### Approve a request

1. An Administrator opens a request that is still Pending Approval.
2. The Administrator selects **Approve**.

The request changes to approved, records the approving Administrator and approval time, and writes an approval event to the security activity log.

The requester cannot approve their own request. The server checks this rule even if someone tries to submit the approval request directly.

### Reject a request

1. An Administrator opens a request that is still Pending Approval and was submitted by another user.
2. Enter a rejection reason.
3. Select **Reject**.

A rejection reason is required. After submission, the request changes to rejected, records the rejection time and reason, and writes a rejection event to the security activity log.

The requester cannot reject their own request.

### Void an approved request

1. An Administrator opens an approved request that has not already been voided.
2. Choose **Void** from the request history actions.
3. Enter the void reason.
4. Confirm the void.

The request is marked voided and the void action is recorded. A voided request is no longer treated as an active approved disbursement.

### Important rules and follow-up

- Amount, recipient or payee, requested date, and purpose are required.
- Supporting reference and notes or remarks are optional.
- Only pending requests can be approved or rejected.
- Only Administrators can approve or reject.
- The requester cannot approve or reject their own request.
- Rejection and void actions require a reason.
- Approved, non-voided disbursements remain available in **Bank Reconciliation → Outgoing Funds** for matching to a bank withdrawal.
- Pending and rejected requests do not enter the approved outgoing-funds list.
