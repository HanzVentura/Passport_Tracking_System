const applicationType = document.body.dataset.applicationType || 'new';
const applicationLabels = {
    new: {
        title: 'PASSPORT APPLICATION',
        category: 'New First-Time Application',
        description: 'To proceed with your New Passport application, please specify the exact service you require.',
        identityDescription: 'Please upload at least one (1) valid primary government-issued ID.',
        citizenshipDescription: 'A clear, scanned copy of your birth certificate is mandatory for first-time applicants.',
        citizenshipLabel: 'Proof of Philippine Citizenship',
        citizenshipType: 'Birth Certificate (PSA)',
        citizenshipNumber: 'Certificate Number',
        citizenshipDate: 'Date of Registration',
        citizenshipPlaceholder: 'Certificate / Barcode No.'
    },
    renewal: {
        title: 'Passport Renewal',
        category: 'Passport Renewal',
        description: 'Provide the details and documents needed to renew your existing Philippine passport.',
        identityDescription: 'Please upload a clear copy of your valid government-issued ID.',
        citizenshipDescription: 'Upload your current or most recently issued Philippine passport.',
        citizenshipLabel: 'Current Passport',
        citizenshipType: 'Current Philippine Passport',
        citizenshipNumber: 'Passport Number',
        citizenshipDate: 'Date of Issue',
        citizenshipPlaceholder: 'Enter passport number'
    }
};
const labels = applicationLabels[applicationType] || applicationLabels.new;
document.addEventListener('DOMContentLoaded', () => {
    document.title = `${labels.title} - Passport Tracking`;

    const headerTitle = document.getElementById('pageHeaderTitle');
    if (headerTitle) headerTitle.textContent = labels.title;

    const greetingText = document.getElementById('greetingText');
    if (greetingText) greetingText.textContent = 'Good Day';

    const categoryDescription = document.getElementById('categoryDescription');
    if (categoryDescription) categoryDescription.textContent = labels.description;

    const identityDesc = document.getElementById('identityDescription');
    if (identityDesc) identityDesc.textContent = labels.identityDescription;

    const citizenshipDesc = document.getElementById('citizenshipDescription');
    if (citizenshipDesc) citizenshipDesc.textContent = labels.citizenshipDescription;

    const citizenshipTitle = document.getElementById('citizenshipTitle');
    if (citizenshipTitle) citizenshipTitle.textContent = labels.citizenshipLabel;

    const citizenshipNumLabel = document.getElementById('citizenshipNumberLabel');
    if (citizenshipNumLabel) citizenshipNumLabel.textContent = labels.citizenshipNumber;

    const citizenshipDateLabel = document.getElementById('citizenshipDateLabel');
    if (citizenshipDateLabel) citizenshipDateLabel.textContent = labels.citizenshipDate;

    const citizenshipNum = document.getElementById('citizenshipNumber');
    if (citizenshipNum) citizenshipNum.placeholder = labels.citizenshipPlaceholder;

    const summaryCategory = document.getElementById('summaryCategory');
    if (summaryCategory) summaryCategory.textContent = labels.category;

    const summaryCitizenship = document.getElementById('summaryCitizenship');
    if (summaryCitizenship) summaryCitizenship.textContent = labels.citizenshipType;
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', () => updateFileName(input.id, input.dataset.nameTarget));
    });

    loadUserData();
    if (typeof goToWizardStep === 'function') {
        goToWizardStep(1);
    }
});
async function loadUserData() {
    try {
        const response = await fetch('../api/user-data.php');
        const data = await response.json();
        if (data.loggedIn && data.fullName) {
            const greeting = document.getElementById('greetingText');
            if (greeting) greeting.textContent = `Good Day, ${data.fullName}`;

            const userNameEl = document.getElementById('user-name');
            if (userNameEl) userNameEl.textContent = data.fullName;

            const summaryFullName = document.getElementById('summaryFullName');
            if (summaryFullName) summaryFullName.textContent = data.fullName;

            if (data.email) localStorage.setItem('userEmail', data.email);
        }
    } catch (error) {
        console.error('Unable to load user data:', error);
    }
}
function updateFileName(inputId, nameId) {
    const fileInput = document.getElementById(inputId);
    const fileName = document.getElementById(nameId);
    if (fileInput && fileName) {
        if (fileInput.files.length > 0) {
            fileName.textContent = fileInput.files[0].name;
            fileName.style.color = '#10b981';
        } else {
            fileName.textContent = 'No file selected';
            fileName.style.color = '#6b7280';
        }
    }
}
function goToWizardStep(stepNumber) {
    document.querySelectorAll('.step-pane').forEach(pane => { pane.hidden = true; });
    document.querySelectorAll('.wizard-step').forEach(step => { step.className = 'wizard-step'; });
    const paneTarget = document.getElementById(`pane${stepNumber}`);
    if (paneTarget) paneTarget.hidden = false;

    const stepCount = applicationType === 'renewal' ? 2 : 3;
    for (let index = 1; index <= stepCount; index += 1) {
        const step = document.getElementById(`wStep${index}`);
        if (step) {
            if (index < stepNumber) step.classList.add('completed');
            if (index === stepNumber) step.classList.add('active');
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function validateAndProceed() {
    const idNumberEl = document.getElementById('idNumber');
    const idExpiryEl = document.getElementById('idExpiry');
    const idFileEl = document.getElementById('idFile');
    const citizenshipNumEl = document.getElementById('citizenshipNumber');
    const citizenshipDateEl = document.getElementById('citizenshipDate');
    const citizenshipFileEl = document.getElementById('citizenshipFile');
    const idComplete = (!idNumberEl || idNumberEl.value.trim())
        && (!idExpiryEl || idExpiryEl.value)
        && (!idFileEl || idFileEl.files.length);

    const citizenshipComplete = (!citizenshipNumEl || citizenshipNumEl.value.trim())
        && (!citizenshipDateEl || citizenshipDateEl.value)
        && (!citizenshipFileEl || citizenshipFileEl.files.length);
    if (!idComplete) {
        alert('Please complete all fields and attach a document for Proof of Identity.');
        return;
    }
    if (!citizenshipComplete) {
        alert(`Please complete all fields and attach a document for ${labels.citizenshipLabel}.`);
        return;
    }
    const selectedId = document.getElementById('idType');
    const summaryIdType = document.getElementById('summaryIdType');
    if (summaryIdType && selectedId) {
        summaryIdType.textContent = selectedId.options[selectedId.selectedIndex].text;
    }
    goToWizardStep(applicationType === 'renewal' ? 2 : 3);
}
async function completePayment() {
    const randomId = `PH-${Math.floor(100000 + Math.random() * 900000)}`;

    const userEmail = localStorage.getItem('userEmail') || document.getElementById('email')?.value;

    if (!userEmail) {
        alert('Please provide your email or log in before saving your application.');
        return;
    }

    // Read the actual application type from the form select (not labels.category)
    const applicationTypeSelect = document.getElementById('applicationTypeSelect');
    const applicationType = applicationTypeSelect ? applicationTypeSelect.value : labels.category;

    try {
        const response = await fetch('../api/submit-application.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: userEmail,
                applicationId: randomId,
                applicationType: applicationType,
                status: 'Form Submitted',
                appointmentDate: localStorage.getItem('selectedAppointmentDate') || '2027-01-01',
                appointmentTime: localStorage.getItem('selectedAppointmentTime') || '15:00',
                appointmentLocation: localStorage.getItem('selectedSite') || 'ANGELES'
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to save your application.');
        }

        const savedApplicationId = result.application_id || result.applicationId || randomId;

        localStorage.setItem('currentApplicationId', savedApplicationId);
        localStorage.setItem('activeAppId', savedApplicationId);
        localStorage.setItem('applicationType', result.applicationType || labels.category);

        window.location.href = 'payments.html?id=' + savedApplicationId;

    } catch (error) {
        console.error('Unable to save application:', error);
        alert(error.message);
    }
}

/**
 * ==========================================
 * DFA RULES ENGINE - R.A. 11983 Compliant
 * Shared by new-passport.html & group-wizard.html
 * ==========================================
 */

// Application Type Options (R.A. 11983)
const APPLICATION_TYPES = [
    { value: 'First-Time Passport', label: 'First-Time Passport' },
    { value: 'Passport Renewal', label: 'Passport Renewal' },
    { value: 'Replacement of Lost Passport', label: 'Replacement of Lost Passport' },
    { value: 'Replacement of Damaged Passport', label: 'Replacement of Damaged Passport' },
    { value: 'Credential Updating (Change of Personal Details)', label: 'Credential Updating (Change of Personal Details)' }
];

// Credential Updating Sub-Reasons
const CREDENTIAL_UPDATING_REASONS = [
    { value: 'marriage', label: 'Marriage (Adopting Spouse\'s Surname)' },
    { value: 'annulment_divorce', label: 'Annulment or Recognized Foreign Divorce' },
    { value: 'widowhood', label: 'Widowhood' },
    { value: 'voluntary_reversion', label: 'Voluntary Reversion to Maiden Name (R.A. 11983)' },
    { value: 'name_correction', label: 'Typographical / Clerical Error or Legal Name Change' },
    { value: 'gender_update', label: 'Gender / Sex Marker Update' }
];

// Resolved human-readable application_type strings for submission & review
function resolveApplicationType(appType, credentialReason) {
    if (appType !== 'Credential Updating (Change of Personal Details)') {
        return appType;
    }
    const reasonMap = {
        'marriage': 'Credential Updating - Marriage',
        'annulment_divorce': 'Credential Updating - Annulment / Foreign Divorce',
        'widowhood': 'Credential Updating - Widowhood',
        'voluntary_reversion': 'Credential Updating - Voluntary Reversion',
        'name_correction': 'Credential Updating - Typo / Legal Name Change',
        'gender_update': 'Credential Updating - Gender Update'
    };
    return reasonMap[credentialReason] || 'Credential Updating (Change of Personal Details)';
}

// Calculate applicant age from DOB and appointment date
function calculateAge(dobStr, appointmentDateStr) {
    if (!dobStr || !appointmentDateStr) return null;
    const dob = new Date(dobStr);
    const appt = new Date(appointmentDateStr);
    if (isNaN(dob.getTime()) || isNaN(appt.getTime())) return null;
    let age = appt.getFullYear() - dob.getFullYear();
    const m = appt.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && appt.getDate() < dob.getDate())) age--;
    return age;
}

// Document requirements per application type/reason/age
const DOCUMENT_REQUIREMENTS = {
    'First-Time Passport': {
        adult: [
            { key: 'psa_birth_cert', label: "PSA Certificate of Live Birth", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: "Valid Primary Government-Issued ID", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' }
        ],
        minor: [
            { key: 'psa_birth_cert', label: "Child's PSA Certificate of Live Birth", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'guardian_valid_id', label: "Valid Passport or Gov't ID of Accompanying Parent/Guardian", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'school_id_or_coe', label: 'School ID or Certificate of Enrollment (Ages 7–17)', required: false, accept: '.pdf,.jpg,.jpeg,.png', note: '', ageMin: 7, ageMax: 17 }
        ]
    },
    'Passport Renewal': {
        adult: [
            { key: 'current_epassport', label: 'Current ePassport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: 'Valid Primary Government-Issued ID', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' }
        ],
        minor: [
            { key: 'current_epassport', label: 'Current ePassport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: 'Valid Primary Government-Issued ID', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'psa_birth_cert', label: "PSA Certificate of Live Birth", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'guardian_valid_id', label: "Valid Passport or Gov't ID of Accompanying Parent/Guardian", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'school_id_or_coe', label: 'School ID or Certificate of Enrollment (Ages 7–17)', required: false, accept: '.pdf,.jpg,.jpeg,.png', note: '', ageMin: 7, ageMax: 17 }
        ]
    },
    'Replacement of Lost Passport': {
        adult: [
            { key: 'psa_birth_cert', label: 'PSA Certificate of Live Birth', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: 'Valid Primary Government-Issued ID', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'affidavit_of_loss', label: 'Notarized Affidavit of Loss (English)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'police_report', label: 'Police Report (English)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' }
        ],
        minor: [
            { key: 'psa_birth_cert', label: "Child's PSA Certificate of Live Birth", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'guardian_valid_id', label: "Valid Passport or Gov't ID of Accompanying Parent/Guardian", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'affidavit_of_loss', label: 'Notarized Affidavit of Loss (English)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'police_report', label: 'Police Report (English)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'school_id_or_coe', label: 'School ID or Certificate of Enrollment (Ages 7–17)', required: false, accept: '.pdf,.jpg,.jpeg,.png', note: '', ageMin: 7, ageMax: 17 }
        ]
    },
    'Replacement of Damaged Passport': {
        adult: [
            { key: 'damaged_passport', label: 'Damaged / Mutilated Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'affidavit_of_damage', label: 'Notarized Affidavit of Explanation / Mutilation', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'psa_birth_cert', label: 'PSA Certificate of Live Birth', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: 'Valid Primary Government-Issued ID', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' }
        ],
        minor: [
            { key: 'damaged_passport', label: 'Damaged / Mutilated Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'affidavit_of_damage', label: 'Notarized Affidavit of Explanation / Mutilation', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'psa_birth_cert', label: "Child's PSA Certificate of Live Birth", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'guardian_valid_id', label: "Valid Passport or Gov't ID of Accompanying Parent/Guardian", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'school_id_or_coe', label: 'School ID or Certificate of Enrollment (Ages 7–17)', required: false, accept: '.pdf,.jpg,.jpeg,.png', note: '', ageMin: 7, ageMax: 17 }
        ]
    },
    'Credential Updating (Change of Personal Details)': {
        marriage: [
            { key: 'current_epassport', label: 'Current Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: 'Valid Primary Government-Issued ID', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'psa_marriage_cert', label: 'PSA Certificate of Marriage', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' }
        ],
        annulment_divorce: [
            { key: 'current_epassport', label: 'Current Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: 'Valid Primary Government-Issued ID', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'psa_birth_cert', label: 'PSA Certificate of Live Birth', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'annotated_psa_marriage_cert', label: 'Annotated PSA Certificate of Marriage', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'court_decree_finality', label: 'Court Decree & Certificate of Finality', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' }
        ],
        widowhood: [
            { key: 'current_epassport', label: 'Current Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: 'Valid Primary Government-Issued ID', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'psa_birth_cert', label: 'PSA Certificate of Live Birth', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'psa_death_cert', label: 'PSA Certificate of Death of Spouse', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'psa_marriage_cert', label: 'PSA Certificate of Marriage', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' }
        ],
        voluntary_reversion: [
            { key: 'current_epassport', label: 'Current Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: "Valid Government-Issued ID (reflecting Maiden Name)", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'psa_birth_cert', label: 'PSA Certificate of Live Birth', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'psa_marriage_cert', label: 'PSA Certificate of Marriage', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'affidavit_of_reversion', label: 'Notarized Affidavit of Explanation for Reversion', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' }
        ],
        name_correction: [
            { key: 'current_epassport', label: 'Current Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: "Valid Primary Government-Issued ID (reflecting corrected details)", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'annotated_psa_birth_cert', label: 'Annotated PSA Certificate of Live Birth', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'ra9048_10172_order', label: 'Civil Registrar Decision (R.A. 9048/10172) or Court Order', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' }
        ],
        gender_update: [
            { key: 'current_epassport', label: 'Current Passport (Data Page)', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'valid_gov_id', label: "Valid Primary Government-Issued ID (reflecting updated entry)", required: true, accept: '.pdf,.jpg,.jpeg,.png', note: 'Note: PhilHealth ID and Pag-IBIG ID are not accepted as valid primary IDs.' },
            { key: 'annotated_psa_birth_cert', label: 'Annotated PSA Certificate of Live Birth', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' },
            { key: 'ra10172_gender_order', label: 'Civil Registrar Decision (with Accredited Gov\'t Physician Cert) or Court Order', required: true, accept: '.pdf,.jpg,.jpeg,.png', note: '' }
        ]
    }
};

// Guidance banner messages per application type/reason
const GUIDANCE_MESSAGES = {
    'First-Time Passport': {
        adult: 'First-time adult applicants must submit a PSA Birth Certificate and a valid primary government-issued ID.',
        minor: 'First-time minor applicants must submit the child\'s PSA Birth Certificate, guardian\'s valid ID, and school ID/COE (ages 7–17).'
    },
    'Passport Renewal': {
        adult: 'Passport renewal requires your current ePassport and a valid primary government-issued ID.',
        minor: 'Minor passport renewal requires current ePassport, PSA Birth Certificate, guardian\'s valid ID, and school ID/COE (ages 7–17).'
    },
    'Replacement of Lost Passport': {
        adult: 'Lost passport replacement requires PSA Birth Certificate, valid primary ID, notarized Affidavit of Loss, and Police Report.',
        minor: 'Minor lost passport replacement requires child\'s PSA Birth Certificate, guardian\'s valid ID, Affidavit of Loss, Police Report, and school ID/COE (ages 7–17).'
    },
    'Replacement of Damaged Passport': {
        adult: 'Damaged passport replacement requires the damaged passport, Affidavit of Mutilation, PSA Birth Certificate, and valid primary ID.',
        minor: 'Minor damaged passport replacement requires damaged passport, Affidavit of Mutilation, child\'s PSA Birth Certificate, guardian\'s valid ID, and school ID/COE (ages 7–17).'
    },
    'Credential Updating (Change of Personal Details)': {
        marriage: 'Credential Updating (Marriage) requires current passport, valid primary ID, and PSA Marriage Certificate.',
        annulment_divorce: 'Credential Updating (Annulment/Foreign Divorce) requires current passport, valid primary ID, PSA Birth Certificate, annotated PSA Marriage Certificate, and Court Decree with Finality.',
        widowhood: 'Credential Updating (Widowhood) requires current passport, valid primary ID, PSA Birth Certificate, PSA Death Certificate of spouse, and PSA Marriage Certificate.',
        voluntary_reversion: 'Credential Updating (Voluntary Reversion) requires current passport, valid ID reflecting maiden name, PSA Birth Certificate, PSA Marriage Certificate, and notarized Affidavit of Reversion.',
        name_correction: 'Credential Updating (Typo/Legal Name Change) requires current passport, valid ID reflecting corrected details, annotated PSA Birth Certificate, and Civil Registrar Decision or Court Order (R.A. 9048/10172).',
        gender_update: 'Credential Updating (Gender Update) requires current passport, valid ID reflecting updated entry, annotated PSA Birth Certificate, and Civil Registrar Decision with Accredited Physician Cert or Court Order (R.A. 10172).'
    }
};

// Get required documents for current selection
function getRequiredDocuments(appType, credentialReason, age) {
    const typeConfig = DOCUMENT_REQUIREMENTS[appType];
    if (!typeConfig) return [];

    let category;
    if (appType === 'Credential Updating (Change of Personal Details)') {
        category = credentialReason;
    } else {
        category = (age !== null && age < 18) ? 'minor' : 'adult';
    }

    const docs = typeConfig[category] || [];
    return docs.filter(doc => {
        if (doc.ageMin !== undefined && doc.ageMax !== undefined && age !== null) {
            return age >= doc.ageMin && age <= doc.ageMax;
        }
        return true;
    });
}

// Get guidance message
function getGuidanceMessage(appType, credentialReason, age) {
    const typeMsgs = GUIDANCE_MESSAGES[appType];
    if (!typeMsgs) return 'Upload clear scanned copies or photos of your supporting documents for automated pre-screening before your appointment.';

    if (appType === 'Credential Updating (Change of Personal Details)') {
        return typeMsgs[credentialReason] || typeMsgs.marriage;
    }
    return (age !== null && age < 18) ? typeMsgs.minor : typeMsgs.adult;
}

// Render dynamic document slots
function renderDynamicDocSlots(containerId, appType, credentialReason, age, prefix = '') {
    const container = document.getElementById(containerId);
    if (!container) return;

    const docs = getRequiredDocuments(appType, credentialReason, age);
    let html = '';

    docs.forEach((doc, index) => {
        const inputId = `${prefix}doc_${doc.key}`;
        const nameId = `${prefix}doc_${doc.key}_name`;
        // File uploads are now optional - no required attribute
        const noteHtml = doc.note ? `<div style="font-size: 11px; color: #ef4444; margin-top: 4px;">${doc.note}</div>` : '';

        html += `
            <div class="form-group" data-doc-key="${doc.key}" data-doc-required="${doc.required}">
                <label>${doc.label} ${doc.required ? '<span style="color:#ef4444;">*</span>' : ''}</label>
                <div class="upload-box">
                    <label class="btn-upload" for="${inputId}"><i class="fa-solid fa-upload"></i> Browse</label>
                    <input type="file" id="${inputId}" name="${doc.key}" accept="${doc.accept}" style="display: none;">
                    <span class="upload-file-name" id="${nameId}">No file attached (to be presented on-site)</span>
                </div>
                ${noteHtml}
            </div>
        `;
    });

    container.innerHTML = html;

    // Bind file input events
    docs.forEach(doc => {
        const inputId = `${prefix}doc_${doc.key}`;
        const nameId = `${prefix}doc_${doc.key}_name`;
        const input = document.getElementById(inputId);
        const span = document.getElementById(nameId);
        if (input && span) {
            input.addEventListener('change', (e) => {
                span.textContent = e.target.files[0] ? e.target.files[0].name : 'No file attached (to be presented on-site)';
                span.style.color = e.target.files[0] ? '#10b981' : '#6b7280';
            });
        }
    });
}

// Update guidance banner
function updateGuidanceBanner(bannerId, appType, credentialReason, age) {
    const banner = document.getElementById(bannerId);
    if (banner) {
        banner.textContent = getGuidanceMessage(appType, credentialReason, age);
    }
}

// Clear all staged files in dynamic doc slots
function clearDynamicDocSlots(containerId, prefix = '') {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.querySelectorAll('input[type="file"]').forEach(input => {
        input.value = '';
        const nameId = `${prefix}doc_${input.name}_name`;
        const span = document.getElementById(nameId);
        if (span) {
            span.textContent = 'No file attached (to be presented on-site)';
            span.style.color = '#6b7280';
        }
    });
}

// Validate Step 5 (Application Type + Credential Reason)
function validateStep5(appTypeSelectId, credentialReasonSelectId) {
    const appTypeSelect = document.getElementById(appTypeSelectId);
    const credentialReasonSelect = document.getElementById(credentialReasonSelectId);
    const credentialReasonContainer = credentialReasonSelect ? credentialReasonSelect.closest('.form-group') : null;

    if (!appTypeSelect) return { valid: false, message: 'Application type select not found' };

    const appType = appTypeSelect.value;

    // Show/hide credential reason dropdown
    if (credentialReasonContainer) {
        if (appType === 'Credential Updating (Change of Personal Details)') {
            credentialReasonContainer.style.display = 'block';
            credentialReasonSelect.required = true;
        } else {
            credentialReasonContainer.style.display = 'none';
            credentialReasonSelect.required = false;
            credentialReasonSelect.value = '';
        }
    }

    if (!appType) {
        return { valid: false, message: 'Please select an Application Type.' };
    }

    if (appType === 'Credential Updating (Change of Personal Details)' && !credentialReasonSelect.value) {
        return { valid: false, message: 'Please select a Reason for Credential Update.' };
    }

    return { valid: true };
}

// Validate Step 6 (Document uploads are now optional - non-blocking)
function validateStep6(containerId) {
    // File uploads are optional; always allow proceeding to review
    return { valid: true };
}

// Get all document data for review/submission
function getDocumentData(containerId, prefix = '') {
    const container = document.getElementById(containerId);
    if (!container) return {};

    const data = {};
    container.querySelectorAll('input[type="file"]').forEach(input => {
        if (input.files && input.files.length > 0) {
            data[input.name] = {
                name: input.files[0].name,
                size: input.files[0].size,
                type: input.files[0].type
            };
        }
    });
    return data;
}

// Get document labels for review display
function getDocumentLabels(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return [];

    const labels = [];
    container.querySelectorAll('.form-group[data-doc-key]').forEach(group => {
        const labelEl = group.querySelector('label');
        const input = group.querySelector('input[type="file"]');
        const nameSpan = group.querySelector('.upload-file-name');
        if (labelEl && input) {
            const fileName = nameSpan ? nameSpan.textContent : 'No file attached (to be presented on-site)';
            labels.push({
                label: labelEl.textContent.replace('*', '').trim(),
                fileName: fileName,
                required: group.dataset.docRequired === 'true'
            });
        }
    });
    return labels;
}

// Expose for global access
window.DFA_RULES_ENGINE = {
    APPLICATION_TYPES,
    CREDENTIAL_UPDATING_REASONS,
    resolveApplicationType,
    calculateAge,
    getRequiredDocuments,
    getGuidanceMessage,
    renderDynamicDocSlots,
    updateGuidanceBanner,
    clearDynamicDocSlots,
    validateStep5,
    validateStep6,
    getDocumentData,
    getDocumentLabels
};

// Auto-initialize on DOMContentLoaded for pages that need it
document.addEventListener('DOMContentLoaded', () => {
    // Initialize Step 5 dropdowns if they exist
    const appTypeSelects = document.querySelectorAll('#applicationTypeSelect, #applicationTypeSelectGroup');
    appTypeSelects.forEach(select => {
        if (select && select.options.length <= 1) {
            // Populate with R.A. 11983 options
            select.innerHTML = '<option value="">- SELECT APPLICATION TYPE -</option>';
            APPLICATION_TYPES.forEach(type => {
                const opt = document.createElement('option');
                opt.value = type.value;
                opt.textContent = type.label;
                select.appendChild(opt);
            });
        }
    });

    // Initialize Credential Reason dropdowns if they exist
    const credReasonSelects = document.querySelectorAll('#credentialReasonSelect, #credentialReasonSelectGroup');
    credReasonSelects.forEach(select => {
        if (select && select.options.length <= 1) {
            select.innerHTML = '<option value="">- SELECT REASON -</option>';
            CREDENTIAL_UPDATING_REASONS.forEach(reason => {
                const opt = document.createElement('option');
                opt.value = reason.value;
                opt.textContent = reason.label;
                select.appendChild(opt);
            });
        }
    });
});