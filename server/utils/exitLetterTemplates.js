const formatDate = (value) => {
  if (!value) return new Date().toLocaleDateString('en-GB');
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? String(value) : d.toLocaleDateString('en-GB');
};

export const getNoDuesCertificateTemplate = (user) => {
  const name = user.username || 'Employee';
  const empId = user.employee_id || '—';
  const role = user.user_type || '—';
  const project = user.project_name || '—';
  const city = user.city || 'Bangalore';
  const lastWorkingDay = formatDate(user.deactivated_at || user.lastWorkingDay);

  return `
  <div style="font-family: Arial, sans-serif; line-height: 1.8; color: #111; padding: 40px; max-width: 800px; margin: auto;">
    <div style="text-align: center; margin-bottom: 50px;">
      <h2 style="text-transform: uppercase; font-weight: bold; text-decoration: underline; letter-spacing: 2px;">No Dues Certificate</h2>
    </div>
    <p style="margin-bottom: 30px; text-align: justify;">
      This is to certify that <strong>${name}</strong> [Employee ID: <strong>${empId}</strong>] who was working as <strong>${role}</strong> in <strong>${project}</strong> at <strong>Search Homes India Pvt Ltd</strong>, has cleared all dues with respect to the company as of their last working day <strong>${lastWorkingDay}</strong>.
    </p>
    <p style="margin-bottom: 30px; text-align: justify;">
      There are no pending financial or material obligations from the employee towards the company, and all company assets have been returned in proper condition. All pending incentives and salaries have also been cleared by the company.
    </p>
    <p style="margin-bottom: 50px; text-align: justify;">
      This certificate is being issued upon the employee's request for future reference.
    </p>
    <div style="margin-top: 60px;">
      <p><strong>Date:</strong> ${lastWorkingDay}</p>
      <p><strong>Place:</strong> ${city}</p>
    </div>
    <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 80px;">
      <tr>
        <td style="width: 50%; text-align: left; vertical-align: bottom; border: none; padding: 0;">
          <div class="hr-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: left; margin-bottom: 5px;"></div>
          <p style="margin: 0; font-weight: bold;">Authorized Signatory</p>
          <p style="margin: 0;"><strong>Shivali V Rai</strong></p>
          <p style="margin: 0;">HR Manager</p>
        </td>
        <td style="width: 50%; text-align: right; vertical-align: bottom; border: none; padding: 0;">
          <div style="display: inline-block; text-align: left;">
            <div class="employee-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: center; border-bottom: 1px solid #111; min-width: 200px; margin-bottom: 5px;"></div>
            <p style="margin: 0; font-weight: bold; text-align: center;">Employee Signature</p>
          </div>
        </td>
      </tr>
    </table>
  </div>`;
};

export const getRelievingLetterTemplate = (user) => {
  const today = new Date().toLocaleDateString('en-GB');
  const name = user.username || 'Employee';
  const empId = user.employee_id || '—';
  const role = user.user_type || '—';
  const doj = formatDate(user.doj);
  const lastWorkingDay = formatDate(user.deactivated_at || user.lastWorkingDay);

  return `
  <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #111; padding: 50px; max-width: 850px; margin: auto;">
    <p style="text-align: left; margin-bottom: 40px;">Date: ${today}</p>
    <div style="text-align: center; margin-bottom: 60px;">
      <h2 style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">TO WHOM SO EVER IT MAY CONCERN</h2>
    </div>
    <p style="margin-bottom: 25px; text-align: justify;">
      This is to certify that <strong>${name} [Employee ID: ${empId}]</strong> was employed with us as a full-time employee from <strong>${doj}</strong> to <strong>${lastWorkingDay}</strong> and has been relieved from their duties as of closing hours on <strong>${lastWorkingDay}</strong>.
    </p>
    <p style="margin-bottom: 25px; text-align: justify;">
      ${name} was designated as <strong>${role}</strong> at the time of leaving the organization.
    </p>
    <p style="margin-bottom: 60px; text-align: justify;">
      We wish ${name} all the best in future endeavors.
    </p>
    <div style="margin-top: 80px;">
      <p style="margin-bottom: 10px;">For Search Homes India Pvt Ltd</p>
      <div class="hr-sig-img" style="min-height: 45px; font-family: 'Dancing Script', cursive; font-size: 28px; color: #1e3a8a; line-height: 45px; text-align: left;"></div>
      <p style="margin: 0;"><strong>Shivali V Rai</strong></p>
      <p style="margin: 0;">Sr. HR Executive</p>
    </div>
  </div>`;
};

export const getLetterTemplate = (type, user) => {
  if (type === 'relieving_letter') return getRelievingLetterTemplate(user);
  return getNoDuesCertificateTemplate(user);
};

export const getLetterTitle = (type) => {
  if (type === 'relieving_letter') return 'Relieving Letter';
  return 'No Dues Letter';
};
