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
        const inputName = `${prefix}${doc.key}`; // Prefixed name for multi-applicant support
        // File uploads are now optional - no required attribute
        const noteHtml = doc.note ? `<div style="font-size: 11px; color: #ef4444; margin-top: 4px;">${doc.note}</div>` : '';

        html += `
            <div class="form-group" data-doc-key="${doc.key}" data-doc-required="${doc.required}">
                <label>${doc.label} ${doc.required ? '<span style="color:#ef4444;">*</span>' : ''}</label>
                <div class="upload-box">
                    <label class="btn-upload" for="${inputId}"><i class="fa-solid fa-upload"></i> Browse</label>
                    <input type="file" id="${inputId}" name="${inputName}" accept="${doc.accept}" style="display: none;">
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
        // input.name is already prefixed (e.g., "applicant_1_psa_birth_cert")
        const nameId = `${prefix}doc_${input.name.replace(prefix, '')}_name`;
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
            // input.name is already prefixed (e.g., "applicant_1_psa_birth_cert")
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
function getDocumentLabels(containerId, prefix = '') {
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
                required: group.dataset.docRequired === 'true',
                key: input.name // Prefixed key (e.g., "applicant_1_psa_birth_cert")
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

// Also expose pure helpers for direct use (parameterized, no DOM assumptions)
window.DFA_RULES_HELPERS = {
    getApplicationTypes: () => APPLICATION_TYPES,
    getCredentialUpdateReasons: () => CREDENTIAL_UPDATING_REASONS,
    resolveRequiredDocuments: (params) => getRequiredDocuments(params.applicationType, params.updateReason, params.age),
    resolveApplicationTypeLabel: (applicationType, updateReason) => resolveApplicationType(applicationType, updateReason)
};

/**
 * ==========================================
 * GROUP BOOKING STATE MANAGEMENT
 * Finite-state loop for multi-applicant wizard
 * ==========================================
 */

// Group booking state (populated by initGroupBookingState)
window.groupBookingState = {
    groupCount: 1,
    currentApplicantIndex: 0,
    groupApplicantsData: [],
    applicantFiles: [],
    isInitialized: false
};

/**
 * FR-1: Clean Group Session Initialization
 * Call this when starting a new group booking (from group-appointment.html or fresh group-wizard.html load)
 * @param {number} groupCount - Number of applicants (1-10)
 */
function initGroupBookingState(groupCount) {
    // Clamp groupCount to 1-10
    const count = Math.max(1, Math.min(10, parseInt(groupCount, 10) || 1));

    // Clear any stale localStorage data
    localStorage.removeItem('groupApplicantsData');

    // Initialize clean state array with completed: false for each applicant
    const cleanApplicantsData = Array.from({ length: count }, () => ({
        completed: false
    }));

    // Initialize in-memory File storage array (parallel to groupApplicantsData)
    const cleanApplicantFiles = Array.from({ length: count }, () => ({}));

    // Update global state
    window.groupBookingState = {
        groupCount: count,
        currentApplicantIndex: 0,
        groupApplicantsData: cleanApplicantsData,
        applicantFiles: cleanApplicantFiles,
        isInitialized: true
    };

    // Also persist the initialized array to localStorage for page reloads
    localStorage.setItem('groupCount', String(count));
    localStorage.setItem('groupApplicantsData', JSON.stringify(cleanApplicantsData));

    // Reset window.applicantFiles for backward compatibility (group-wizard.html uses this)
    window.applicantFiles = {};
    for (let i = 0; i < count; i++) {
        const prefix = `applicant_${i + 1}_`;
        window.applicantFiles[prefix] = {};
    }

    console.log('[GroupBooking] Initialized clean state for', count, 'applicants');
    return window.groupBookingState;
}

/**
 * FR-2: Single State Commit Point
 * Flush all Step 1-6 fields and in-memory File objects into groupApplicantsData[index] / applicantFiles[index]
 * Call this on every forward or backward applicant transition
 * @param {number} index - Applicant index (0-based)
 */
function commitApplicantToState(index) {
    const state = window.groupBookingState;
    if (!state.isInitialized || index < 0 || index >= state.groupCount) {
        console.warn('[GroupBooking] commitApplicantToState: invalid index', index);
        return false;
    }

    const prefix = `applicant_${index + 1}_`;
    const form = document.getElementById('passportForm');
    if (!form) return false;

    const formData = new FormData(form);
    let appData = { ...state.groupApplicantsData[index] }; // Preserve existing data (like completed flag)

    // Save all form fields for this applicant
    formData.forEach((value, key) => {
        // Skip hidden fields that are not applicant-specific
        if (key === 'appointment_mode' || key === 'site' || key === 'app_date' || key === 'app_time' || key === 'group_data_payload') {
            return;
        }
        // Only save fields that belong to current applicant (prefixed) or shared fields
        if (key.startsWith(prefix) || !key.includes('_')) {
            appData[key] = value;
        }
    });

    // Save shared Site/Schedule from Applicant 1 (index 0) to all applicants
    if (index === 0) {
        const siteSelect = document.getElementById('siteSelect');
        const selectedDateVal = document.getElementById('selectedDateVal');
        const appTimeSelect = document.getElementById('appTimeSelect');
        if (siteSelect) appData['site_selection'] = siteSelect.value;
        if (selectedDateVal) appData['app_date_selection'] = selectedDateVal.value;
        if (appTimeSelect) appData['app_time_selection'] = appTimeSelect.value;
    } else {
        // Copy shared data from applicant 1
        const app1 = state.groupApplicantsData[0] || {};
        appData['site_selection'] = app1['site_selection'] || '';
        appData['app_date_selection'] = app1['app_date_selection'] || '';
        appData['app_time_selection'] = app1['app_time_selection'] || '';
    }

    // Mark as completed
    appData.completed = true;

    // Update state
    state.groupApplicantsData[index] = appData;

    // Commit in-memory File objects for this applicant
    if (window.applicantFiles[prefix]) {
        state.applicantFiles[index] = { ...window.applicantFiles[prefix] };
    }

    // Persist to localStorage
    localStorage.setItem('groupApplicantsData', JSON.stringify(state.groupApplicantsData));

    console.log('[GroupBooking] Committed applicant', index + 1, 'of', state.groupCount, appData);
    return true;
}

/**
 * FR-2/FR-5: Hydrate form for a specific applicant
 * Populate form fields from groupApplicantsData[index] and restore file indicators from applicantFiles[index]
 * Re-render Step 5/6 controls for the applicant
 * @param {number} index - Applicant index (0-based)
 */
function hydrateApplicantForm(index) {
    const state = window.groupBookingState;
    if (!state.isInitialized || index < 0 || index >= state.groupCount) {
        console.warn('[GroupBooking] hydrateApplicantForm: invalid index', index);
        return false;
    }

    const prefix = `applicant_${index + 1}_`;
    const appData = state.groupApplicantsData[index] || {};
    const form = document.getElementById('passportForm');
    if (!form) return false;

    // Update current applicant index in state
    state.currentApplicantIndex = index;

    // Update group banner
    updateGroupBanner();

    // Update Step 6 forward button label
    updateStep6ButtonLabel();

    // Clear all form inputs first (except shared Site/Schedule fields)
    clearApplicantFormFields(prefix);

    // Populate form fields from saved data
    Object.entries(appData).forEach(([key, value]) => {
        if (key === 'completed') return;
        const input = form.querySelector(`[name="${key}"]`);
        if (input) {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = (value === 'on' || value === true || value === 'true');
            } else {
                input.value = value;
            }
        }
    });

    // Restore shared Site/Schedule display for Applicant 1
    if (index === 0) {
        const siteSelect = document.getElementById('siteSelect');
        const selectedDateVal = document.getElementById('selectedDateVal');
        const appTimeSelect = document.getElementById('appTimeSelect');
        const officeName = document.getElementById('officeName');
        const officeContact = document.getElementById('officeContact');
        const officeAddress = document.getElementById('officeAddress');

        if (siteSelect && appData.site_selection) {
            siteSelect.value = appData.site_selection;
            // Trigger updateSiteInfo to show office details
            if (typeof updateSiteInfo === 'function') updateSiteInfo();
        }
        if (selectedDateVal && appData.app_date_selection) {
            selectedDateVal.value = appData.app_date_selection;
            // Re-render calendar to show selected date
            if (typeof renderCalendar === 'function') {
                const [year, month, day] = appData.app_date_selection.split('-').map(Number);
                currentYear = year;
                currentMonth = month - 1;
                renderCalendar();
            }
        }
        if (appTimeSelect && appData.app_time_selection) {
            appTimeSelect.value = appData.app_time_selection;
        }
    }

    // Trigger conditional field visibility (credential reason, minor fields, citizenship basis others)
    triggerConditionalFields();

    // Re-render Step 5/6 dynamic controls for this applicant
    refreshApplicantDocumentSlots(index);

    // Restore file indicators from in-memory storage
    restoreFileIndicators(index);

    console.log('[GroupBooking] Hydrated form for applicant', index + 1, 'of', state.groupCount);
    return true;
}

/**
 * Clear applicant-specific form fields (preserve shared fields)
 * @param {string} prefix - Applicant prefix (e.g., "applicant_1_")
 */
function clearApplicantFormFields(prefix) {
    const form = document.getElementById('passportForm');
    if (!form) return;

    // Clear all inputs that start with the prefix
    form.querySelectorAll('input, select, textarea').forEach(input => {
        const name = input.name;
        if (name && name.startsWith(prefix)) {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else if (input.type === 'file') {
                // File inputs handled separately via restoreFileIndicators
                input.value = '';
            } else {
                input.value = '';
            }
        }
    });

    // Clear dynamic document slots container
    const docContainer = document.getElementById('dynamicDocSlotsGroup');
    if (docContainer) docContainer.innerHTML = '';

    // Hide conditional containers
    const credContainer = document.getElementById('credentialReasonContainerGroup');
    if (credContainer) credContainer.style.display = 'none';
    const minorContainer = document.getElementById('minorFieldsContainer');
    if (minorContainer) minorContainer.style.display = 'none';
    const othersContainer = document.getElementById('othersBasisContainerGroup');
    if (othersContainer) othersContainer.style.display = 'none';
}

/**
 * Trigger conditional field visibility based on current form values
 */
function triggerConditionalFields() {
    // Application type -> credential reason
    const appTypeSelect = document.getElementById('applicationTypeSelectGroup');
    const credContainer = document.getElementById('credentialReasonContainerGroup');
    const credSelect = document.getElementById('credentialReasonSelectGroup');
    if (appTypeSelect && credContainer && credSelect) {
        if (appTypeSelect.value === 'Credential Updating (Change of Personal Details)') {
            credContainer.style.display = 'block';
            credSelect.required = true;
        } else {
            credContainer.style.display = 'none';
            credSelect.required = false;
            credSelect.value = '';
        }
    }

    // DOB -> minor fields
    const dobInput = document.getElementById('dobInput');
    const minorContainer = document.getElementById('minorFieldsContainer');
    if (dobInput && minorContainer && dobInput.value) {
        const dob = new Date(dobInput.value);
        const today = new Date(2026, 8, 20); // Reference date
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        if (age < 18) {
            minorContainer.style.display = 'block';
            minorContainer.querySelectorAll('input').forEach(input => input.required = true);
        } else {
            minorContainer.style.display = 'none';
            minorContainer.querySelectorAll('input').forEach(input => input.required = false);
        }
    }

    // Citizenship basis -> others field
    const citizenshipSelect = document.getElementById('citizenshipBasisSelectGroup');
    const othersContainer = document.getElementById('othersBasisContainerGroup');
    if (citizenshipSelect && othersContainer) {
        othersContainer.style.display = citizenshipSelect.value === 'OTHERS' ? 'block' : 'none';
    }
}

/**
 * Refresh document slots for a specific applicant (re-render Step 5/6 controls)
 * @param {number} index - Applicant index (0-based)
 */
function refreshApplicantDocumentSlots(index) {
    const state = window.groupBookingState;
    const prefix = `applicant_${index + 1}_`;
    const appData = state.groupApplicantsData[index] || {};

    const appType = appData.application_type || document.getElementById('applicationTypeSelectGroup')?.value || '';
    const credentialReason = appData.credential_reason || document.getElementById('credentialReasonSelectGroup')?.value || '';
    const dob = appData.dob || document.getElementById('dobInput')?.value || '';
    const appointmentDate = appData.app_date_selection || document.getElementById('selectedDateVal')?.value || '';

    const age = window.DFA_RULES_ENGINE.calculateAge(dob, appointmentDate);

    // Clear stale dynamic slots
    window.DFA_RULES_ENGINE.clearDynamicDocSlots('dynamicDocSlotsGroup', prefix);

    // Clear stored File objects for this applicant when type/reason changes (handled by commit/refresh cycle)
    if (window.applicantFiles[prefix]) {
        delete window.applicantFiles[prefix];
    }

    // Render new document slots with applicant prefix
    window.DFA_RULES_ENGINE.renderDynamicDocSlots('dynamicDocSlotsGroup', appType, credentialReason, age, prefix);

    // Bind file change events to store File objects per applicant
    const container = document.getElementById('dynamicDocSlotsGroup');
    if (container) {
        container.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', (e) => {
                if (!window.applicantFiles[prefix]) {
                    window.applicantFiles[prefix] = {};
                }
                const docKey = input.name; // Already prefixed (e.g., "applicant_1_psa_birth_cert")
                if (e.target.files[0]) {
                    window.applicantFiles[prefix][docKey] = e.target.files[0];
                } else {
                    delete window.applicantFiles[prefix][docKey];
                }
            });
        });
    }

    // Update guidance banner
    window.DFA_RULES_ENGINE.updateGuidanceBanner('docRequirementsNoticeGroup', appType, credentialReason, age);
}

/**
 * Restore file indicators from in-memory storage for a specific applicant
 * @param {number} index - Applicant index (0-based)
 */
function restoreFileIndicators(index) {
    const state = window.groupBookingState;
    const prefix = `applicant_${index + 1}_`;
    const savedFiles = state.applicantFiles[index] || {};

    // Restore to window.applicantFiles for compatibility
    window.applicantFiles[prefix] = { ...savedFiles };

    // Update file name displays
    Object.entries(savedFiles).forEach(([docKey, file]) => {
        const nameId = `${prefix}doc_${docKey.replace(prefix, '')}_name`;
        const span = document.getElementById(nameId);
        if (span && file) {
            span.textContent = file.name;
            span.style.color = '#10b981';
        }
    });
}

/**
 * Update the group banner showing current applicant progress
 */
function updateGroupBanner() {
    const state = window.groupBookingState;
    const badgeDiv = document.getElementById('groupApplicantBadge');
    if (badgeDiv) {
        badgeDiv.textContent = `Group Booking: Filling out details for Applicant ${state.currentApplicantIndex + 1} of ${state.groupCount}`;
    }
}

/**
 * Update Step 6 forward button label based on current applicant position
 */
function updateStep6ButtonLabel() {
    const state = window.groupBookingState;
    const btn = document.querySelector('#step6 .btn-primary');
    if (!btn) return;

    if (state.currentApplicantIndex < state.groupCount - 1) {
        const nextNum = state.currentApplicantIndex + 2;
        btn.innerHTML = `Next Applicant (Applicant ${nextNum} of ${state.groupCount}) → <i class="fa-solid fa-arrow-right"></i>`;
        btn.onclick = handleStep6Next;
    } else {
        btn.innerHTML = `Review Group Application → <i class="fa-solid fa-arrow-right"></i>`;
        btn.onclick = handleStep6Next;
    }
}

/**
 * FR-2: Sequential Applicant Loop on Step 6 Forward Action
 * Validates Step 6, commits current applicant, advances to next applicant or Step 7
 */
function handleStep6Next() {
    const state = window.groupBookingState;

    // Validate Step 6 for current applicant
    const validation = window.DFA_RULES_ENGINE.validateStep6('dynamicDocSlotsGroup');
    if (!validation.valid) {
        alert(validation.message);
        return;
    }

    // Commit current applicant data
    if (!commitApplicantToState(state.currentApplicantIndex)) {
        alert('Failed to save applicant data. Please try again.');
        return;
    }

    // Check if this is the last applicant
    if (state.currentApplicantIndex < state.groupCount - 1) {
        // Advance to next applicant
        const nextIndex = state.currentApplicantIndex + 1;

        // Navigate to the entry step for the next applicant (Step 3 - Personal Info)
        // Steps 1-2 are shared, so applicants 2+ start at Step 3
        goToStep(3);

        // Hydrate form for next applicant
        hydrateApplicantForm(nextIndex);

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        // Last applicant - go to Step 7 (Review)
        renderStep7Review();
        goToStep(7);
    }
}

/**
 * FR-3: Step 7 Access Guard
 * Enhanced goToStep with validation for Step 7 access
 * @param {number} stepNumber - Target step (1-7)
 */
function goToStep(stepNumber) {
    const state = window.groupBookingState;

    // FR-3: Guard Step 7 access
    if (stepNumber === 7 && state.isInitialized) {
        const allCompleted = state.groupApplicantsData.every((app, idx) => app && app.completed === true);
        if (!allCompleted) {
            // Find first incomplete applicant
            const firstIncomplete = state.groupApplicantsData.findIndex(app => !app || app.completed !== true);
            alert(`Please complete details and documents for all ${state.groupCount} applicants before reviewing (Applicant ${firstIncomplete + 1} of ${state.groupCount} is incomplete).`);
            return;
        }
    }

    // Hide all step panes
    document.querySelectorAll('.step-pane').forEach(pane => pane.classList.remove('active'));

    // Show target step pane
    const targetPane = document.getElementById('step' + stepNumber);
    if (targetPane) targetPane.classList.add('active');

    // Update wizard step indicators
    for (let i = 1; i <= 7; i++) {
        const wStep = document.getElementById('wStep' + i);
        if (wStep) wStep.classList.toggle('active', i === stepNumber);
    }

    // If navigating to Step 7, render review
    if (stepNumber === 7) {
        renderStep7Review();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * FR-4: Accurate Step 7 Group Review Rendering
 * Iterate over all applicants and render cards with actual data
 */
function renderStep7Review() {
    const state = window.groupBookingState;
    const reviewGrid = document.getElementById('groupReviewGrid');
    if (!reviewGrid) return;

    let htmlContent = '';

    for (let i = 0; i < state.groupCount; i++) {
        const app = state.groupApplicantsData[i] || {};
        const prefix = `applicant_${i + 1}_`;
        const applicantFiles = state.applicantFiles[i] || {};

        const fullName = `${app.givenname || ''} ${app.lastname || ''}`.trim() || 'Not provided';

        // Use shared site/schedule from applicant 1
        const app1 = state.groupApplicantsData[0] || {};
        const site = app.site_selection || app1.site_selection || 'Not selected';
        const date = app.app_date_selection || app1.app_date_selection || 'Not selected';
        const time = app.app_time_selection || app1.app_time_selection || 'Not selected';

        // Resolved application type
        const appType = app.application_type || 'Not selected';
        const credReason = app.credential_reason || '';
        const resolvedType = window.DFA_RULES_HELPERS.resolveApplicationTypeLabel(appType, credReason);

        // Get required documents for this applicant
        const dob = app.dob || '';
        const apptDate = app.app_date_selection || app1.app_date_selection || '';
        const age = window.DFA_RULES_ENGINE.calculateAge(dob, apptDate);
        const docs = window.DFA_RULES_ENGINE.getRequiredDocuments(appType, credReason, age);

        // Build document list with attached file names
        let docHtml = '';
        if (docs.length > 0) {
            docHtml = '<ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 12px; line-height: 1.8; color: #334155;">';
            docs.forEach(doc => {
                const fileKey = `${prefix}${doc.key}`;
                const fileObj = applicantFiles[fileKey];
                const fileName = fileObj ? fileObj.name : 'No file attached (to be presented on-site)';
                const fileColor = fileObj ? '#10b981' : '#6b7280';
                docHtml += `<li><strong>${doc.label}</strong> ${doc.required ? '<span style="color:#ef4444;">*</span>' : ''}: <span style="color:${fileColor};">${fileName}</span></li>`;
            });
            docHtml += '</ul>';
        } else {
            docHtml = '<div style="font-size: 12px; color: #64748b;">No documents required for this application type.</div>';
        }

        htmlContent += `
            <div class="summary-card" style="background: #f8fafc; border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-sm); grid-column: span 2; margin-bottom: 15px;">
                <h4 style="font-size: 15px; color: var(--primary); margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; font-weight: 700; text-transform: uppercase;">Applicant ${i + 1}: ${fullName}</h4>
                <div style="font-size: 13px; line-height: 1.8; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; color: #334155;">
                    <div>
                        <div><strong>Application Type:</strong> ${normalizeAppTypeForDisplay(resolvedType)}</div>
                        <div><strong>Email:</strong> ${app.email || 'Not provided'}</div>
                        <div><strong>Mobile:</strong> ${app.mobile || 'Not provided'}</div>
                    </div>
                    <div>
                        <div><strong>DOB:</strong> ${app.dob || 'Not provided'}</div>
                        <div><strong>Site:</strong> ${site}</div>
                        <div><strong>Date/Time:</strong> ${date} (${time})</div>
                    </div>
                </div>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border-color);">
                    <strong style="font-size: 12px; color: #64748b;">Required Documents:</strong>
                    ${docHtml}
                </div>
            </div>
        `;
    }

    reviewGrid.innerHTML = htmlContent;
}

/**
 * FR-5: Back Navigation from Step 7
 * Returns to last applicant's Step 6 with saved data intact
 */
function handleBackFromStep7() {
    const state = window.groupBookingState;
    if (!state.isInitialized) return;

    // Go to last applicant's Step 6
    const lastIndex = state.groupCount - 1;
    goToStep(6);
    hydrateApplicantForm(lastIndex);
}

/**
 * FR-5: Back Navigation from first per-applicant step (Step 3)
 * Goes to previous applicant's Step 6
 * @param {number} targetStep - The step user clicked back to (usually 2)
 */
function handleBackFromApplicantEntry(targetStep) {
    const state = window.groupBookingState;
    if (!state.isInitialized) return;

    // If we're at Step 3 (first per-applicant step) and not on first applicant
    if (targetStep < 3 && state.currentApplicantIndex > 0) {
        const prevIndex = state.currentApplicantIndex - 1;
        goToStep(6);
        hydrateApplicantForm(prevIndex);
        return true; // Handled
    }
    return false; // Not handled, let normal goToStep proceed
}

// Expose group booking functions globally
window.GroupBooking = {
    initGroupBookingState,
    commitApplicantToState,
    hydrateApplicantForm,
    handleStep6Next,
    goToStep,
    renderStep7Review,
    handleBackFromStep7,
    handleBackFromApplicantEntry,
    updateGroupBanner,
    updateStep6ButtonLabel
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