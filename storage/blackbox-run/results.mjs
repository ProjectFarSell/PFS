const result=(status,actual,remarks)=>[actual,status,remarks];
const F=(a,r)=>result('Fail',a,r), P=(a,r)=>result('Pass',a,r), B=(a,r)=>result('Blocked',a,r);
const http='HTTP run BB-20261018; isolated SQLite.';
const gap='Compared with supplied expected result; original expectation retained.';
const otp=B('Not executed: no OTP is issued and no verification page is registered.','Blocked by missing OTP workflow (A9); route inspection and HTTP 404.');
const phone=F('Registration accepted every supplied phone value; the phone was not saved.','Phone field absent from signup. Format, length and uniqueness rules unavailable.');
const required=F('Empty signup returned required errors for name, email and password only.','Other mandatory fields in the workbook are absent. No account created.');
const omit=F('Omitting name/email/password blocked signup; omitting phone, DOB or terms did not.','Separate names and other workbook fields absent. Confirm mismatch is on password.');
const invalidName=F('Signup accepted names containing digits and symbols (HTTP 302).','Current full-name rule permits any string up to 120 characters.');
const nameLength=F('Names of 1, 2, 50 and 51 characters were all accepted.','One full-name field; maximum 120, no minimum. Workbook limits not enforced.');
const uppercase=F('Uppercase version of the existing email created another account.','SQLite test database: case-sensitive uniqueness. Other database collations untested.');
const complexity=F('All four passwords missing a required character type were accepted.','Minimum 8 characters and confirmation only; complexity not required.');
const password=P('7-character password rejected; 8-character password accepted.','HTTP 422 minimum-length message, then HTTP 302.');
const mismatch=P('Mismatched confirmation rejected with a password confirmation error.','HTTP 422; no account created for this attempt.');
const visibility=P('Password type changed password → text → password using show/hide.','Live browser check on the shared registration form; both role intents use it.');
const terms=F('Signup succeeded without accepting any terms (HTTP 302).','No terms checkbox or acceptance validation exists on signup.');
const rapid=B('Rapid-click scenario not executed; required OTP email workflow is absent.','Single-account concurrency and email count remain unverified.');
const unverified=F('New account with null email_verified_at could log in and open home.','HTTP 302 to home and HTTP 200; no verification gate.');
const cross=B('Existing admin/seller/rider boundaries denied access; logistics page absent.','RoleIsolationTest passed. Full workbook case blocked by missing logistics role.');
export const results={
 Buyer:[
  F('Signup created a buyer, logged in immediately and redirected to home.','No OTP or Unverified-to-Active flow. Name mapped to full name; extra fields ignored.'),
  required,omit,invalidName,
  P("Niño Angelo Peñaflor-O'Hara was accepted and saved unchanged as full name.",'First and last names mapped to the current single name field.'),
  nameLength,
  F('DOB values for under 18, exactly 18 and over 18 all allowed signup.','DOB is ignored. Boundary date: 2026-10-18, Asia/Manila.'),
  F('Tomorrow (2026-10-19) allowed signup; no DOB picker is present.','DOB is ignored; no date or age validation.'),
  F('4 invalid formats rejected; juanmiguel.delacruz@example was accepted.','Single-label domain accepted. Fails the workbook expectation for all 5 inputs.'),
  P('Existing exact email rejected: The email has already been taken.','HTTP 422; no additional account for the exact duplicate.'),
  uppercase,
  P('Email with surrounding spaces saved as buyer.test01@example.com.',http),
  phone,phone,phone,phone,password,complexity,mismatch,visibility,terms,rapid,
  otp,otp,otp,otp,otp,unverified,
  B('Login works for the new account, but it cannot be verified through OTP.','Exact precondition blocked by TC-BUY-001; unverified login checked separately.'),
  cross,
 ],
 Seller:[
  F('Signup created a buyer account and redirected to a separate seller application.','No OTP; not the workbook’s combined seller registration flow.'),
  required,omit,invalidName,
  P("Niña Corazon Peñaranda-D'Souza was accepted and saved as full name.",'Mapped separate test names to one full-name field; accents preserved.'),
  nameLength,
  F('Invalid formats rejected except mariaclara.santos@example, which was accepted.','HTTP run used the seller-specific email data on the shared signup endpoint.'),
  P('Existing exact seller-applicant email rejected as already taken.','HTTP 422; no duplicate user for exact email.'),
  uppercase,
  P('Email with surrounding spaces saved as seller.test01@example.com.',http),
  phone,phone,phone,phone,password,complexity,mismatch,visibility,terms,rapid,
  F('Two different applicants submitted Maria Clara Handicrafts successfully.','HTTP 302 for both; duplicate pending shop names persisted.'),
  F('All supplied shop names accepted, including 2-character MC and the 31-character name.','Current maximum 120; no minimum. Workbook’s 3–30 character range is not enforced.'),
  F('Application accepted without documents; no seller-type document controls exist.','Current application collects shop name, city, tagline and description only.'),
  F('Corporation-labelled application submitted without an SEC certificate.','HTTP 302. Seller type is ignored and there is no SEC upload requirement.'),
  F('Application accepted an invalid letter-containing TIN; value was ignored.','TIN is not collected, validated or stored; expected format rule unavailable.'),
  F('TIN uniqueness cannot be enforced: seller application does not store a TIN.','Source/form inspection. Business identifiers are absent from current workflow.'),
  F('Seller application has a city text field, with no dependent location dropdowns.','Registration and seller form inspected. Account-address dropdowns are a separate flow.'),
  F('Application accepted postal code 11A1; the submitted value was ignored.','Postal code absent from seller application. HTTP 302.'),
  B('No seller document upload controls or storage flow exist to run these file tests.','Accepted-file scenario blocked; OTP continuation is also unavailable.'),
  B('Seller upload rejection cannot be exercised: no document field exists.','No JPG/PNG/PDF validation on seller application.'),
  B('Seller upload size boundaries cannot be exercised: no document field exists.','No seller file upload endpoint; 5 MB acceptance remains unverified.'),
  otp,otp,otp,otp,otp,unverified,
  P('Pending applicant sees review status; seller dashboard/product access denied.','SellerApprovalTest passed; pending applicant remains buyer until admin approval.'),
  F('Pending list/details work, but workbook business details and documents are absent.','SellerApprovalTest passed for listing/details; complete document review unavailable.'),
  F('Approval creates shop, grants seller role and enables dashboard; no email is implemented.','Approval/dashboard tested. Notification absence confirmed by controller inspection.'),
  F('Rejection stores/shows reason and withholds seller access; no email is implemented.','Rejection/resubmission tests passed. Notification absent in controller.'),
  B('Text application resubmission works; replacing DTI certificate is unavailable.','SellerApprovalTest passed for text edits; no document replacement workflow.'),
  P('Buyer access to seller approval list/detail/review denied with HTTP 403.','SellerApprovalTest: only_admin_can_review_and_approval_atomically_creates_one_owned_shop.'),
  cross,
 ],
 Logistics:Array.from({length:44},(_,i)=>i===0
  ?F('No logistics signup role, dashboard or application flow exists; intent rejected.','HTTP 422 for logistics intent; route list has no logistics endpoints.')
  :B('Not executed: no logistics account or company workflow can be created.','Blocked by TC-LOG-001. Logistics role absent; no approval, OTP or rider-management flow.')),
 Rider:Array.from({length:32},(_,i)=>{
  const n=i+1;
  if(n===1)return F('No company Add Rider flow exists; buyers self-apply and admins approve riders.','Current rider flow differs from A13; no company list or activation email.');
  if(n===29)return F('Public signup offers Deliver & earn and routes a new buyer to rider application.','HTTP 302 to /rider/apply; signup UI exposes rider intent.');
  if(n===30)return F('A buyer can open the current rider application form (HTTP 200).','The workbook expects denial; project intentionally permits buyer self-application.');
  if(n>=20&&n<=27)return B('Not executed: Pending Activation and emailed activation links do not exist.','Blocked by TC-RID-001. Current rider flow uses admin approval.');
  if(n===28||n===31)return B('Not executed: logistics companies and company-owned rider lists do not exist.','Blocked by missing logistics role and company rider creation.');
  if(n===32)return B('Existing rider/admin/seller role tests passed; exact activation setup unavailable.','Full case also requires logistics dashboard, which is absent.');
  return B('Not executed on the required company Add Rider form: that workflow is absent.','Blocked by TC-RID-001. Self-application tests do not establish this company-created flow.');
 }),
};

export const actualRules=[
 'Signup: full name, email, password/confirmation, buyer/seller/rider intent. Seller details collected separately. No logistics signup.',
 'One full-name string, required, max 120. No alphabet-only rule or minimum length.',
 'Valid email, max 180, exact uniqueness; trimmed. Case-variant duplicate accepted in SQLite; other collations untested.',
 'Signup ignores phone. Rider application phone is optional; max 30 and broad phone syntax; no unique or PH-only rule.',
 'Minimum 8 characters and matching confirmation. Upper/lower/number/symbol combination not required.',
 'No signup DOB or age check. Future and under-18 dates were ignored.',
 'Seller application: free-text city only. No registration region/province/barangay/postal fields.',
 'Seller application has no TIN field or uniqueness validation. No logistics application.',
 'No active OTP routes or verification gate. New accounts log in immediately with null email_verified_at.',
 'Rider self-application: optional JPG/JPEG/PNG/PDF uploads, max 5120 KB. No seller upload flow. Company-created rider flow absent.',
 'No seller-type selector or conditional ID/DTI/SEC/BIR uploads.',
 'Buyer active immediately. Seller application: pending → approved/rejected; approval grants seller role; text edits can resubmit. No review email.',
 'Buyer self-applies as rider. Admin approves/rejects; approval grants rider role. No logistics ownership or activation links.',
 'Seller shop name required, max 120; duplicate applications accepted. No company/SEC fields; rider license/plate not validated as unique.',
 'Rider self-application license number: required string, max 40; no format/expiry fields. Company rider creation absent.',
 'Buyer/seller/rider/admin route checks enforced in existing tests. Logistics role does not exist; full five-role comparison blocked.',
];
