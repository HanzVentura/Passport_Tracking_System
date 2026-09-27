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
        title: 'PASSPORT RENEWAL',
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
    const userEmail = localStorage.getItem('userEmail');
    if (!userEmail) {
        alert('Please log in before saving your application.');
        return;
    }
    try {
        const response = await fetch('../api/applications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: userEmail,
                applicationId: randomId,
                applicationType: labels.category,
                status: 'Form Submitted',
                appointmentDate: localStorage.getItem('selectedAppointmentDate') || '2027-01-01',
                appointmentTime: localStorage.getItem('selectedAppointmentTime') || '15:00',
                appointmentLocation: localStorage.getItem('selectedSite') || 'CANDON'
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
        window.location.href = 'payments.html';
    } catch (error) {
        console.error('Unable to save application:', error);
        alert(error.message);
    }
}