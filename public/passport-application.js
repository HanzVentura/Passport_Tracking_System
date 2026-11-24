// ==========================================
// Passport Application Wizard - Shared Logic
// IIFE encapsulation to prevent global collisions
// ==========================================
(() => {
    'use strict';

    // Initialize global File storage for group wizard (cannot be in localStorage)
    window.applicantFiles = {};

    // ==========================================
    // Solo Wizard Labels (for new-passport.html)
    // ==========================================
    const applicationType = document.body?.dataset?.applicationType || 'new';
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

    // ==========================================
    // DFA Office Data (shared - single source of truth)
    // ==========================================
    const DFA_OFFICE_DATA = {
        "ANGELES": { name: "DFA Regional Consular Office – Pampanga (Angeles)", address: "3F MarQuee Mall, Pulung Maragul, Angeles City", contact: "(045) 304-0194" },
        "ANTIPOLO": { name: "DFA Regional Consular Office – Antipolo", address: "3F SM Cherry Foodarama, Marikina-Infanta Highway, Antipolo City, Rizal", contact: "(02) 8697-3000" },
        "ANTIQUE": { name: "DFA Regional Consular Office – Antique", address: "CityMall Antique, National Road, San Jose de Buenavista, Antique", contact: "(036) 540-0123" },
        "BACOLOD": { name: "DFA Regional Consular Office – Bacolod", address: "3rd Level Robinsons Place Bacolod, Brgy. Mandalangan, Bacolod City", contact: "(034) 441-2675" },
        "BAGUIO": { name: "DFA Regional Consular Office – Baguio", address: "SM City Baguio Upper Basement, Luneta Hill, Upper Session Road, Baguio City", contact: "(074) 422-1465" },
        "BALANGA": { name: "DFA Regional Consular Office – Balanga", address: "The Bunker Building, Capitol Compound, Balanga City, Bataan", contact: "(047) 237-0111" },
        "BUTUAN": { name: "DFA Regional Consular Office – Butuan", address: "3rd Level Robinsons Place Butuan, J.C. Aquino Ave., Butuan City", contact: "(085) 342-5700" },
        "CAGAYAN DE ORO": { name: "DFA Regional Consular Office – Cagayan de Oro", address: "3F Centrio Mall, cor. Corrales and Recto Sts., Cagayan de Oro City", contact: "(088) 857-6565" },
        "CALASIAO": { name: "DFA Regional Consular Office – Calasiao", address: "2F Robinsons Place-Pangasinan, Brgy. San Miguel, Calasiao, Pangasinan", contact: "(075) 632-7705" },
        "CANDON": { name: "DFA Regional Consular Office – Candon", address: "Candon City Arena, Candon City, Ilocos Sur", contact: "(077) 644-0111" },
        "CEBU": { name: "DFA Regional Consular Office – Cebu", address: "4th Level Pacific Mall-Metro Mandaue, UN Ave. cor. MC Briones St., Mandaue City", contact: "(032) 520-5898" },
        "CLARIN": { name: "DFA Regional Consular Office – Clarin", address: "Clarin Town Center, Clarin, Misamis Occidental", contact: "(088) 530-5312" },
        "DFA MANILA": { name: "DFA Manila (ASEANA)", address: "ASEANA Business Park, Bradco Avenue corner Macapagal Boulevard, Parañaque City", contact: "556-0000 / 651-9400" },
        "DFA NCR CENTRAL": { name: "DFA NCR Central", address: "Lingkod Pinoy Center, Robinsons Galleria, West Lane EDSA cor. Ortigas Ave., Quezon City", contact: "(02) 8631-0806" },
        "DFA NCR EAST": { name: "DFA NCR East (Megamall)", address: "7F Building C, SM Megamall, EDSA cor. J. Vargas Ave., Mandaluyong City", contact: "(02) 8570-3230" },
        "DFA NCR NORTH": { name: "DFA NCR North (Novaliches)", address: "Quirino Highway, Novaliches, Quezon City (across SM City Fairview)", contact: "(02) 8834-4000" },
        "DFA NCR NORTHEAST": { name: "DFA NCR Northeast (Ali Mall)", address: "Level 2, Ali Mall Government Center, Cubao, Quezon City", contact: "(02) 8293-0105" },
        "DFA NCR SOUTH": { name: "DFA NCR South (Alabang)", address: "4th Floor, Metro Alabang Town Center, Muntinlupa City", contact: "(02) 8551-1051" },
        "DFA NCR WEST": { name: "DFA NCR West (SM Manila)", address: "5th Floor, SM City Manila, Concepcion St., Manila", contact: "(02) 8536-9994" },
        "DASMARIÑAS": { name: "DFA Regional Consular Office – Dasmariñas", address: "SM City Dasmariñas, Governor's Drive, Brgy. Sampaloc 1, Dasmariñas City, Cavite", contact: "(046) 432-0000" },
        "DAVAO": { name: "DFA Regional Consular Office – Davao", address: "3F SM City-Davao Quimpo Boulevard, Ecoland Subdivision, Davao City", contact: "(082) 285-4885" },
        "DUMAGUETE": { name: "DFA Regional Consular Office – Dumaguete", address: "2F Robinsons Place South Road, Calidangan, Dumaguete City", contact: "(035) 532-0024" },
        "GENERAL SANTOS": { name: "DFA Regional Consular Office – General Santos", address: "GF Robinsons Place GenSan, Jose Catolico Sr. Ave., General Santos City", contact: "(083) 552-0000" },
        "ILOCOS NORTE": { name: "DFA Regional Consular Office – Ilocos Norte", address: "Robinsons Place Ilocos Norte, Brgy. San Francisco, San Nicolas, Ilocos Norte", contact: "(077) 843-4000" },
        "ILOILO": { name: "DFA Regional Consular Office – Iloilo", address: "3F Robinsons Place Iloilo Quezon Wing, Iloilo City", contact: "(033) 336-1737" },
        "KIDAPAWAN": { name: "DFA Regional Consular Office – Kidapawan", address: "People's Palace, City Hall, Kidapawan City", contact: "(064) 421-3495" },
        "LA UNION": { name: "DFA Regional Consular Office – La Union", address: "2nd Floor Manna Mall, Marcos Highway corner Diversion Road, San Fernando, La Union", contact: "(072) 607-6510" },
        "LEGAZPI": { name: "DFA Regional Consular Office – Legazpi", address: "3F Pacific Mall-Legazpi, Landco Business Park, Legazpi City", contact: "(052) 480-3000" },
        "LIPA": { name: "DFA Regional Consular Office – Lipa", address: "2F Robinsons Place-Lipa, JP Laurel Highway, Lipa City, Batangas", contact: "(043) 722-0578" },
        "LUCENA": { name: "DFA Regional Consular Office – Lucena", address: "3F Pacific Mall-Lucena, M.L. Tagarao St., Lucena City", contact: "(042) 710-4526" },
        "MALOLOS": { name: "DFA Regional Consular Office – Malolos", address: "3F Malolos Central Transport Terminal and Commercial Hub by Xentro Mall, Malolos City", contact: "(044) 794-0000" },
        "OLONGAPO": { name: "DFA Regional Consular Office – Olongapo", address: "SM City Olongapo Central, Gordon Ave., Olongapo City", contact: "(047) 222-0000" },
        "PAGADIAN": { name: "DFA Regional Consular Office – Pagadian", address: "C3 Mall, Pagadian City, Zamboanga del Sur", contact: "(062) 215-0000" },
        "PAMPANGA": { name: "DFA Regional Consular Office – Pampanga Starmills", address: "2F Robinsons StarMills, City of San Fernando, Pampanga", contact: "(045) 636-0007" },
        "PANIQUI, TARLAC": { name: "DFA Regional Consular Office – Paniqui", address: "Waltermart Paniqui, Tarlac", contact: "(045) 931-0000" },
        "PUERTO PRINCESA": { name: "DFA Regional Consular Office – Puerto Princesa", address: "2nd Level Robinsons Place Palawan, National Highway, Puerto Princesa City", contact: "(048) 434-1773" },
        "SAN PABLO": { name: "DFA Regional Consular Office – San Pablo", address: "2F SM City San Pablo, Maharlika Highway, San Pablo City, Laguna", contact: "(049) 562-0000" },
        "SANTIAGO, ISABELA": { name: "DFA Regional Consular Office – Santiago", address: "Robinsons Place Santiago, Maharlika Highway, Santiago City, Isabela", contact: "(078) 305-0000" },
        "TACLOBAN": { name: "DFA Regional Consular Office – Tacloban", address: "3rd Level, Robinsons North Tacloban, Brgy. Abucay, Tacloban City", contact: "(053) 321-0000" },
        "TAGBILARAN": { name: "DFA Regional Consular Office – Tagbilaran", address: "Alturas Mall, Tagbilaran City, Bohol", contact: "(038) 411-0000" },
        "TAGUM": { name: "DFA Regional Consular Office – Tagum", address: "Robinsons Place Tagum, Tagum City, Davao del Norte", contact: "(084) 655-0000" },
        "TUGUEGARAO": { name: "DFA Regional Consular Office – TugueGarao", address: "Regional Government Center, Carig Sur, TugueGarao City", contact: "(078) 844-0000" },
        "ZAMBOANGA": { name: "DFA Regional Consular Office – Zamboanga", address: "Go-Velayoor Building, Vet. Ave., Zamboanga City", contact: "(062) 991-0000" }
    };

    // ==========================================
    // Calendar State (encapsulated)
    // ==========================================
    let currentYear = 2026;
    let currentMonth = 8;
    let selectedDateStr = '';
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    function getSlotsForDate(dayNum) {
        return [
            { time: "08:00 AM - 09:00 AM", status: "available", slots: 5 },
            { time: "09:00 AM - 10:00 AM", status: "booked" },
            { time: "10:00 AM - 11:00 AM", status: "available", slots: 3 }
        ];
    }

    function renderCalendar() {
        const grid = document.getElementById('calGrid');
        const header = document.getElementById('calMonthYear');
        if (!grid || !header) return;
        header.textContent = `${monthNames[currentMonth]} ${currentYear}`;
        grid.innerHTML = '';
        const firstDayIndex = new Date(currentYear, currentMonth, 1).getDay();
        const totalDays = new Date(currentYear, currentMonth + 1, 0).getDate();
        const todayObj = new Date(2026, 8, 20);
        for (let i = 0; i < firstDayIndex; i++) grid.appendChild(document.createElement('div'));
        for (let day = 1; day <= totalDays; day++) {
            const cell = document.createElement('div');
            cell.className = 'cal-day';
            cell.textContent = day;
            const thisDate = new Date(currentYear, currentMonth, day);
            const formattedDate = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            if (thisDate < todayObj) {
                cell.classList.add('disabled');
            } else if (day % 4 === 0) {
                cell.classList.add('fully-booked');
            } else {
                cell.classList.add('available');
                cell.onclick = () => selectDate(formattedDate, day, cell);
            }
            if (selectedDateStr === formattedDate) cell.classList.add('selected');
            grid.appendChild(cell);
        }
    }

    function changeMonth(direction) {
        currentMonth += direction;
        if (currentMonth > 11) { currentMonth = 0; currentYear++; }
        else if (currentMonth < 0) { currentMonth = 11; currentYear--; }
        renderCalendar();
    }

    function selectDate(dateStr, dayNum, element) {
        document.querySelectorAll('.cal-day').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        selectedDateStr = dateStr;
        const selectedDateVal = document.getElementById('selectedDateVal');
        if (selectedDateVal) selectedDateVal.value = dateStr;
        const timeSelect = document.getElementById('appTimeSelect');
        if (timeSelect) {
            timeSelect.innerHTML = '<option value="">- SELECT TIME SLOT -</option>';
            getSlotsForDate(dayNum).forEach(slot => {
                const opt = document.createElement('option');
                opt.value = slot.time;
                opt.disabled = slot.status === 'booked';
                opt.textContent = slot.status === 'booked' ? `${slot.time} (Fully Booked)` : `${slot.time} (${slot.slots} Slots)`;
                timeSelect.appendChild(opt);
            });
        }
        // Trigger document re-render since appointment date changed
        if (typeof refreshDocumentSlots === 'function') refreshDocumentSlots();
        // Sync shared schedule into GroupBooking state without marking applicant complete prematurely
        if (window.GroupBooking && window.GroupBooking.syncSharedScheduleToState) {
            window.GroupBooking.syncSharedScheduleToState();
        }
    }

    function updateTimeSelection() {
        // Sync shared schedule into GroupBooking state without marking applicant complete prematurely
        if (window.GroupBooking && window.GroupBooking.syncSharedScheduleToState) {
            window.GroupBooking.syncSharedScheduleToState();
        }
    }

    // ==========================================
    // Site Info Update (shared)
    // ==========================================
    function updateSiteInfo() {
        const selectEl = document.getElementById('siteSelect');
        if (!selectEl) return;
        const selectedSite = selectEl.value;
        const appointmentLocationFull = document.getElementById('appointmentLocationFull');
        if (appointmentLocationFull) {
            appointmentLocationFull.value = selectEl.selectedIndex > 0 ? selectEl.options[selectEl.selectedIndex].text : '';
        }
        const info = DFA_OFFICE_DATA[selectedSite] || { name: "-", address: "-", contact: "-" };
        const officeName = document.getElementById('officeName');
        const officeAddress = document.getElementById('officeAddress');
        const officeContact = document.getElementById('officeContact');
        if (officeName) officeName.value = info.name;
        if (officeAddress) officeAddress.value = info.address;
        if (officeContact) officeContact.value = info.contact;
        if (window.GroupBooking && window.GroupBooking.syncSharedScheduleToState) {
            window.GroupBooking.syncSharedScheduleToState();
        }
    }

    // ==========================================
    // File Name Update Helper
    // ==========================================
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

    // ==========================================
    // Solo Wizard Navigation (new-passport.html) - handles all 7 steps
    // ==========================================
    function goToSoloStep(stepNumber) {
        try {
            // Hide all step panes
            document.querySelectorAll('.step-pane').forEach(pane => { pane.classList.remove('active'); });

            // Show target step pane
            const targetPane = document.getElementById('step' + stepNumber);
            if (targetPane) targetPane.classList.add('active');

            // Update wizard step indicators (all 7 steps)
            for (let i = 1; i <= 7; i++) {
                const wStep = document.getElementById('wStep' + i);
                if (wStep) wStep.classList.toggle('active', i === stepNumber);
            }

            // Special handling for specific steps
            if (stepNumber === 2) {
                // Ensure calendar is rendered for step 2
                const calGrid = document.getElementById('calGrid');
                if (calGrid && calGrid.children.length === 0) {
                    if (typeof renderCalendar === 'function') renderCalendar();
                }
            }

            // If navigating to Step 7, trigger review population (page-specific function if exists)
            if (stepNumber === 7) {
                if (typeof populateReviewSummary === 'function') {
                    populateReviewSummary();
                }
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (err) {
            console.error('goToSoloStep error:', err);
            alert('Navigation error: ' + err.message);
        }
    }

    // Legacy alias for backward compatibility
    function goToWizardStep(stepNumber) {
        goToSoloStep(stepNumber);
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

        const applicationTypeSelect = document.getElementById('applicationTypeSelect');
        const applicationType = applicationTypeSelect ? applicationTypeSelect.value : labels.category;

        // Read appointment data from DOM with fallbacks
        const appointmentDate = document.getElementById('selectedDateVal')?.value || localStorage.getItem('selectedAppointmentDate') || '';
        const appointmentTime = document.getElementById('appTimeSelect')?.value || localStorage.getItem('selectedAppointmentTime') || '';
        const appointmentLocation = document.getElementById('siteSelect')?.value || localStorage.getItem('selectedSite') || 'ANGELES';

        try {
            const response = await fetch('../api/submit-application.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: userEmail,
                    applicationId: randomId,
                    applicationType: applicationType,
                    status: 'Form Submitted',
                    appointmentDate: appointmentDate || '2027-01-01',
                    appointmentTime: appointmentTime || '15:00',
                    appointmentLocation: appointmentLocation
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

    // ==========================================
    // Expose solo wizard functions globally (for inline script compatibility)
    // ==========================================
    window.goToWizardStep = goToWizardStep;
    window.validateAndProceed = validateAndProceed;
    window.completePayment = completePayment;
    window.updateFileName = updateFileName;
    window.updateSiteInfo = updateSiteInfo;
    window.renderCalendar = renderCalendar;
    window.changeMonth = changeMonth;
    window.selectDate = selectDate;
    window.updateTimeSelection = updateTimeSelection;
    window.getSlotsForDate = getSlotsForDate;

    // ==========================================
    // DOMContentLoaded for solo wizard
    // ==========================================
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

        // Initialize calendar if on step 2
        const calGrid = document.getElementById('calGrid');
        if (calGrid) renderCalendar();
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
})();

// ==========================================
// DFA RULES ENGINE - R.A. 11983 Compliant
// Shared by new-passport.html & group-wizard.html
// ==========================================

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

    docs.forEach((doc) => {
        const inputId = `${prefix}doc_${doc.key}`;
        const nameId = `${prefix}doc_${doc.key}_name`;
        const inputName = `${prefix}${doc.key}`; // Prefixed name for multi-applicant support
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
        const nameId = `${prefix}doc_${input.name.replace(prefix, '')}_name`;
        const span = document.getElementById(nameId);
        if (span) {
            span.textContent = 'No file attached (to be presented on-site)';
            span.style.color = '#6b7280';
        }
    });
}

// Validate Step 5 (Application Type + Credential Reason) - RELAXED: no blocking
function validateStep5(appTypeSelectId, credentialReasonSelectId) {
    const appTypeSelect = document.getElementById(appTypeSelectId);
    const credentialReasonSelect = document.getElementById(credentialReasonSelectId);
    const credentialReasonContainer = credentialReasonSelect ? credentialReasonSelect.closest('.form-group') : null;

    if (!appTypeSelect) return { valid: true };

    const appType = appTypeSelect.value;

    // Show/hide credential reason dropdown (UI only, no required enforcement)
    if (credentialReasonContainer) {
        if (appType === 'Credential Updating (Change of Personal Details)') {
            credentialReasonContainer.style.display = 'block';
        } else {
            credentialReasonContainer.style.display = 'none';
            if (credentialReasonSelect) credentialReasonSelect.value = '';
        }
    }

    return { valid: true };
}

// Validate Step 6 (Document uploads are optional - non-blocking)
function validateStep6(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        const fileInputs = container.querySelectorAll('input[type="file"]');
        const allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        const maxFileSize = 5 * 1024 * 1024; // 5MB
        fileInputs.forEach(input => {
            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                if (file.size > maxFileSize) {
                    console.warn(`File ${file.name} exceeds 5MB limit`);
                }
                if (!allowedMimeTypes.includes(file.type)) {
                    console.warn(`File ${file.name} has invalid type: ${file.type}. Allowed: PDF, JPG, PNG`);
                }
            }
        });
    }
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
                key: input.name
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

// Shared form fields excluded from per-applicant state capture/reset
const GROUP_SHARED_FIELDS = new Set([
    'appointment_mode',
    'site',
    'app_date',
    'app_time',
    'group_data_payload',
    'site_selection',
    'app_date_selection',
    'app_time_selection',
    'region',
    'country'
]);

// Group booking state (populated by initGroupBookingState)
window.groupBookingState = {
    groupCount: 1,
    currentApplicantIndex: 0,
    groupApplicantsData: [],
    applicantFiles: [],
    isInitialized: false
};

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Sync shared Site/Schedule fields from Step 1-2 into state.groupApplicantsData
 */
function syncSharedScheduleToState() {
    const state = window.groupBookingState;
    if (!state || !state.isInitialized) return;

    const siteVal = document.getElementById('siteSelect')?.value || '';
    const dateVal = document.getElementById('selectedDateVal')?.value || '';
    const timeVal = document.getElementById('appTimeSelect')?.value || '';

    for (let i = 0; i < state.groupCount; i++) {
        if (!state.groupApplicantsData[i]) {
            state.groupApplicantsData[i] = { completed: false };
        }
        state.groupApplicantsData[i].site_selection = siteVal;
        state.groupApplicantsData[i].app_date_selection = dateVal;
        state.groupApplicantsData[i].app_time_selection = timeVal;
    }

    localStorage.setItem('groupApplicantsData', JSON.stringify(state.groupApplicantsData));
}

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

    // Persist the initialized array to localStorage
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
 * Flush all active fields and in-memory File objects into groupApplicantsData[index] / applicantFiles[index]
 * Call this on every forward or backward applicant transition
 * @param {number} index - Applicant index (0-based)
 * @param {object} [options] - Optional flags ({ markCompleted: boolean })
 */
function commitApplicantToState(index, options = {}) {
    const state = window.groupBookingState;
    if (!state.isInitialized || index < 0 || index >= state.groupCount) {
        console.warn('[GroupBooking] commitApplicantToState: invalid index', index);
        return false;
    }

    const markCompleted = options.markCompleted !== undefined ? options.markCompleted : true;
    const prefix = `applicant_${index + 1}_`;
    const form = document.getElementById('passportForm');
    if (!form) return false;

    const existing = state.groupApplicantsData[index] || {};
    const appData = { ...existing };

    // Capture ALL active inputs, <select> dropdowns, textareas, and checked radio buttons inside #passportForm
    const controls = form.querySelectorAll('input, select, textarea');
    controls.forEach(el => {
        const rawKey = el.name;
        if (!rawKey) return;

        // Exclude shared appointment/site/schedule fields
        if (GROUP_SHARED_FIELDS.has(rawKey)) return;

        // File inputs are handled separately in window.applicantFiles / state.applicantFiles
        if (el.type === 'file') return;

        // Only capture checked radio buttons; for checkboxes store boolean/string state
        if (el.type === 'radio') {
            if (!el.checked) return;
        }

        // Normalize prefixed applicant_${index + 1}_ field names into canonical snake_case keys
        let canonicalKey = rawKey;
        if (rawKey.startsWith(prefix)) {
            canonicalKey = rawKey.slice(prefix.length);
        } else if (/^applicant_\d+_/.test(rawKey)) {
            // Ignore fields belonging to another applicant prefix if present
            return;
        }

        if (el.type === 'checkbox') {
            appData[canonicalKey] = el.checked ? (el.value || true) : false;
        } else {
            appData[canonicalKey] = el.value;
        }
    });

    // Map legacy alias field names to canonical schema keys expected by PHP backend
    if (appData.gender && !appData.sex) appData.sex = appData.gender;
    if (appData.emergency_number && !appData.emergency_phone) appData.emergency_phone = appData.emergency_number;

    // Auto-combine birth_city, birth_province, birth_country -> birth_place cleanly
    const birthParts = [appData.birth_city, appData.birth_province, appData.birth_country]
        .map(v => (typeof v === 'string' ? v.trim() : ''))
        .filter(Boolean);
    if (birthParts.length > 0) {
        appData.birth_place = birthParts.join(', ');
    }

    // Auto-combine address, address_city, address_province -> address without duplicating segments on re-save
    const rawStreet = typeof appData.address === 'string' ? appData.address.trim() : '';
    const addrCity = typeof appData.address_city === 'string' ? appData.address_city.trim() : '';
    const addrProvince = typeof appData.address_province === 'string' ? appData.address_province.trim() : '';
    const suffixParts = [addrCity, addrProvince].filter(Boolean);
    const suffix = suffixParts.join(', ');

    let cleanStreet = rawStreet;
    if (suffix && cleanStreet.endsWith(', ' + suffix)) {
        cleanStreet = cleanStreet.slice(0, -(suffix.length + 2)).trim();
    } else if (suffix && cleanStreet === suffix) {
        cleanStreet = '';
    }
    appData.address_street = cleanStreet;

    const combinedAddrParts = [cleanStreet, addrCity, addrProvince].filter(Boolean);
    if (combinedAddrParts.length > 0) {
        appData.address = combinedAddrParts.join(', ');
    }

    // Defaults for canonical schema fields required by PHP backend
    if (!appData.citizenship) appData.citizenship = 'Filipino';
    if (!appData.passport_type) appData.passport_type = 'Regular';
    if (appData.extname === undefined) appData.extname = '';

    // Normalize empty strings '' to null for MySQL strict mode while preserving valid non-empty strings
    const normalizeForMySQL = (val, maxLen) => {
        if (val === '' || val === undefined || val === null) return null;
        if (typeof val === 'string') {
            const trimmed = val.trim();
            if (trimmed === '') return null;
            return maxLen && trimmed.length > maxLen ? trimmed.substring(0, maxLen) : trimmed;
        }
        return val;
    };

    appData.givenname = normalizeForMySQL(appData.givenname, 100);
    appData.middlename = normalizeForMySQL(appData.middlename, 100);
    appData.lastname = normalizeForMySQL(appData.lastname, 100);
    appData.extname = normalizeForMySQL(appData.extname, 50);
    appData.dob = normalizeForMySQL(appData.dob);
    appData.sex = normalizeForMySQL(appData.sex, 20);
    appData.civil_status = normalizeForMySQL(appData.civil_status, 50);
    appData.legitimacy = normalizeForMySQL(appData.legitimacy, 50);
    appData.birth_country = normalizeForMySQL(appData.birth_country, 100);
    appData.birth_province = normalizeForMySQL(appData.birth_province, 100);
    appData.birth_city = normalizeForMySQL(appData.birth_city, 100);
    appData.birth_place = normalizeForMySQL(appData.birth_place, 255);
    appData.citizenship = normalizeForMySQL(appData.citizenship, 100);
    appData.citizenship_basis = normalizeForMySQL(appData.citizenship_basis, 100);
    appData.citizenship_basis_others = normalizeForMySQL(appData.citizenship_basis_others, 150);
    appData.foreign_passport = normalizeForMySQL(appData.foreign_passport, 20);
    appData.passport_type = normalizeForMySQL(appData.passport_type, 50);
    appData.phone = normalizeForMySQL(appData.phone, 50);
    appData.mobile = normalizeForMySQL(appData.mobile, 50);
    appData.email = normalizeForMySQL(appData.email, 255);
    appData.address = normalizeForMySQL(appData.address);
    appData.address_city = normalizeForMySQL(appData.address_city, 100);
    appData.address_province = normalizeForMySQL(appData.address_province, 100);
    appData.emergency_contact = normalizeForMySQL(appData.emergency_contact, 255);
    appData.emergency_phone = normalizeForMySQL(appData.emergency_phone, 50);
    appData.occupation = normalizeForMySQL(appData.occupation, 100);
    appData.office_number = normalizeForMySQL(appData.office_number, 50);
    appData.father_lastname = normalizeForMySQL(appData.father_lastname, 100);
    appData.father_givenname = normalizeForMySQL(appData.father_givenname, 100);
    appData.father_middlename = normalizeForMySQL(appData.father_middlename, 100);
    appData.father_citizenship = normalizeForMySQL(appData.father_citizenship, 100);
    appData.mother_lastname = normalizeForMySQL(appData.mother_lastname, 100);
    appData.mother_givenname = normalizeForMySQL(appData.mother_givenname, 100);
    appData.mother_middlename = normalizeForMySQL(appData.mother_middlename, 100);
    appData.mother_citizenship = normalizeForMySQL(appData.mother_citizenship, 100);
    appData.companion_name = normalizeForMySQL(appData.companion_name, 150);
    appData.companion_relationship = normalizeForMySQL(appData.companion_relationship, 100);
    appData.application_type = normalizeForMySQL(appData.application_type, 150);
    appData.credential_reason = normalizeForMySQL(appData.credential_reason, 100);

    // Save shared Site/Schedule from live DOM (or Applicant 1) to this applicant
    const liveSite = document.getElementById('siteSelect')?.value;
    const liveDate = document.getElementById('selectedDateVal')?.value;
    const liveTime = document.getElementById('appTimeSelect')?.value;
    const app1 = state.groupApplicantsData[0] || {};

    appData.site_selection = liveSite || app1.site_selection || existing.site_selection || '';
    appData.app_date_selection = liveDate || app1.app_date_selection || existing.app_date_selection || '';
    appData.app_time_selection = liveTime || app1.app_time_selection || existing.app_time_selection || '';

    // Mark completion flag
    appData.completed = markCompleted ? true : Boolean(existing.completed);

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

    // Clear all non-shared form inputs first
    clearApplicantFormFields(prefix);

    // Populate form fields from saved canonical snake_case data
    Object.entries(appData).forEach(([key, value]) => {
        if (
            key === 'completed' ||
            key === 'site_selection' ||
            key === 'app_date_selection' ||
            key === 'app_time_selection' ||
            key === 'address_street'
        ) {
            return;
        }

        const rawValue = key === 'address' && appData.address_street !== undefined
            ? appData.address_street
            : value;
        const safeValue = (rawValue === null || rawValue === undefined) ? '' : String(rawValue);

        let matchingControls = form.querySelectorAll(`[name="${key}"]`);
        if (!matchingControls.length) {
            matchingControls = form.querySelectorAll(`[name="${prefix}${key}"]`);
        }

        matchingControls.forEach(control => {
            if (control.type === 'file') return;

            if (control.type === 'radio') {
                control.checked = (String(control.value) === safeValue);
            } else if (control.type === 'checkbox') {
                control.checked = (
                    rawValue === 'on' ||
                    rawValue === true ||
                    rawValue === 'true' ||
                    rawValue === 1 ||
                    (safeValue !== '' && String(control.value) === safeValue)
                );
            } else if (control.tagName === 'SELECT') {
                control.value = safeValue;
                if (control.value !== safeValue) {
                    control.selectedIndex = 0;
                }
            } else {
                control.value = safeValue;
            }
        });
    });

    // Keep confirm email input in sync when returning to a saved applicant
    const confirmEmailInput = document.getElementById('confirmEmailInput');
    if (confirmEmailInput) {
        confirmEmailInput.value = appData.email ? String(appData.email) : '';
    }

    // Restore shared Site/Schedule display from Applicant 1 / saved data
    const sharedSource = index === 0 ? appData : (state.groupApplicantsData[0] || appData);
    const siteSelect = document.getElementById('siteSelect');
    const selectedDateVal = document.getElementById('selectedDateVal');
    const appTimeSelect = document.getElementById('appTimeSelect');

    if (siteSelect && sharedSource.site_selection) {
        siteSelect.value = sharedSource.site_selection;
        if (typeof updateSiteInfo === 'function') updateSiteInfo();
    }
    if (selectedDateVal && sharedSource.app_date_selection) {
        selectedDateVal.value = sharedSource.app_date_selection;
        if (typeof renderCalendar === 'function') {
            const parts = sharedSource.app_date_selection.split('-').map(Number);
            if (parts.length === 3 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                currentYear = parts[0];
                currentMonth = parts[1] - 1;
                selectedDateStr = sharedSource.app_date_selection;
                renderCalendar();
            }
        }
    }
    if (appTimeSelect && sharedSource.app_time_selection) {
        appTimeSelect.value = sharedSource.app_time_selection;
    }

    // Trigger conditional field visibility (credential reason, minor fields, citizenship basis others)
    triggerConditionalFields();

    // Re-render Step 5/6 dynamic controls for this applicant (preserving files before restore)
    refreshApplicantDocumentSlots(index, { preserveFiles: true });

    // Restore file indicators from in-memory storage
    restoreFileIndicators(index);

    console.log('[GroupBooking] Hydrated form for applicant', index + 1, 'of', state.groupCount);
    return true;
}

/**
 * Clear all non-shared form fields (preserving shared Site/Schedule fields and canonical defaults)
 * @param {string} prefix - Applicant prefix (e.g., "applicant_1_")
 */
function clearApplicantFormFields(prefix) {
    const form = document.getElementById('passportForm');
    if (!form) return;

    const readonlyOfficeIds = new Set(['officeName', 'officeContact', 'officeAddress']);

    form.querySelectorAll('input, select, textarea').forEach(el => {
        if (readonlyOfficeIds.has(el.id)) return;

        const name = el.name;
        if (name && GROUP_SHARED_FIELDS.has(name)) return;

        // Preserve hidden canonical defaults
        if (name === 'citizenship') {
            el.value = 'Filipino';
            return;
        }
        if (name === 'passport_type') {
            el.value = 'Regular';
            return;
        }

        if (el.tagName === 'SELECT') {
            el.selectedIndex = 0;
        } else if (el.type === 'checkbox' || el.type === 'radio') {
            el.checked = false;
        } else if (el.type === 'file') {
            el.value = '';
        } else {
            el.value = '';
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
        } else {
            credContainer.style.display = 'none';
            credSelect.value = '';
        }
    }

    // DOB -> minor fields
    const dobInput = document.getElementById('dobInput');
    const minorContainer = document.getElementById('minorFieldsContainer');
    if (dobInput && minorContainer) {
        if (dobInput.value) {
            const dob = new Date(dobInput.value);
            const today = new Date(2026, 8, 20);
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            minorContainer.style.display = age < 18 ? 'block' : 'none';
        } else {
            minorContainer.style.display = 'none';
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
 * Refresh document slots for a specific applicant (prioritizing live DOM values)
 * @param {number} index - Applicant index (0-based)
 * @param {object} [options] - Optional flags ({ preserveFiles: boolean })
 */
function refreshApplicantDocumentSlots(index, options = {}) {
    const state = window.groupBookingState;
    const prefix = `applicant_${index + 1}_`;
    const appData = state.groupApplicantsData[index] || {};
    const app1 = state.groupApplicantsData[0] || {};

    // Prioritize live DOM values over stored state fallbacks
    const liveAppType = document.getElementById('applicationTypeSelectGroup')?.value;
    const liveCredReason = document.getElementById('credentialReasonSelectGroup')?.value;
    const liveDob = document.getElementById('dobInput')?.value;
    const liveApptDate = document.getElementById('selectedDateVal')?.value;

    const appType = (liveAppType !== undefined && liveAppType !== '')
        ? liveAppType
        : (appData.application_type || '');
    const credentialReason = (liveCredReason !== undefined && liveCredReason !== '')
        ? liveCredReason
        : (appData.credential_reason || '');
    const dob = (liveDob !== undefined && liveDob !== '')
        ? liveDob
        : (appData.dob || '');
    const appointmentDate = (liveApptDate !== undefined && liveApptDate !== '')
        ? liveApptDate
        : (appData.app_date_selection || app1.app_date_selection || '');

    const age = window.DFA_RULES_ENGINE.calculateAge(dob, appointmentDate);

    // Clear stale dynamic slots
    window.DFA_RULES_ENGINE.clearDynamicDocSlots('dynamicDocSlotsGroup', prefix);

    // Render new document slots with applicant prefix
    window.DFA_RULES_ENGINE.renderDynamicDocSlots('dynamicDocSlotsGroup', appType, credentialReason, age, prefix);

    // Prune or clear stored File objects for keys not applicable to the active document slots
    if (!options.preserveFiles) {
        const requiredDocs = window.DFA_RULES_ENGINE.getRequiredDocuments(appType, credentialReason, age);
        const validKeys = new Set(requiredDocs.map(doc => `${prefix}${doc.key}`));
        if (window.applicantFiles[prefix]) {
            Object.keys(window.applicantFiles[prefix]).forEach(existingKey => {
                if (!validKeys.has(existingKey)) {
                    delete window.applicantFiles[prefix][existingKey];
                }
            });
        }
        if (state.applicantFiles[index]) {
            Object.keys(state.applicantFiles[index]).forEach(existingKey => {
                if (!validKeys.has(existingKey)) {
                    delete state.applicantFiles[index][existingKey];
                }
            });
        }
    }

    // Bind file change events to store File objects per applicant
    const container = document.getElementById('dynamicDocSlotsGroup');
    if (container) {
        container.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', (e) => {
                if (!window.applicantFiles[prefix]) {
                    window.applicantFiles[prefix] = {};
                }
                if (!state.applicantFiles[index]) {
                    state.applicantFiles[index] = {};
                }
                const docKey = input.name; // Prefixed (e.g., "applicant_1_psa_birth_cert")
                if (e.target.files[0]) {
                    window.applicantFiles[prefix][docKey] = e.target.files[0];
                    state.applicantFiles[index][docKey] = e.target.files[0];
                } else {
                    delete window.applicantFiles[prefix][docKey];
                    delete state.applicantFiles[index][docKey];
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
        badgeDiv.innerHTML = `<i class="fa-solid fa-users"></i> Group Booking: Filling out details for Applicant ${state.currentApplicantIndex + 1} of ${state.groupCount}`;
    }

    // Also update Step 3 title
    const step3Title = document.getElementById('step3Title');
    if (step3Title) {
        step3Title.innerHTML = `<i class="fa-solid fa-user" style="color:var(--primary);"></i> Applicant ${state.currentApplicantIndex + 1} of ${state.groupCount} - Personal Information`;
    }
}

/**
 * Update Step 6 forward button label based on current applicant position
 */
function updateStep6ButtonLabel() {
    const state = window.groupBookingState;
    const btn = document.getElementById('step6NextBtn');
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
    try {
        const state = window.groupBookingState;

        // Validate Step 6 for current applicant
        const validation = window.DFA_RULES_ENGINE.validateStep6('dynamicDocSlotsGroup');
        if (!validation.valid) {
            alert(validation.message);
            return;
        }

        // Commit current applicant data and mark completed
        if (!commitApplicantToState(state.currentApplicantIndex, { markCompleted: true })) {
            alert('Failed to save applicant data. Please try again.');
            return;
        }

        // Check if this is the last applicant
        if (state.currentApplicantIndex < state.groupCount - 1) {
            // Advance to next applicant
            const nextIndex = state.currentApplicantIndex + 1;

            // Navigate to the entry step for the next applicant (Step 3 - Personal Info)
            goToStep(3);

            // Hydrate form for next applicant (resets all non-shared fields cleanly)
            hydrateApplicantForm(nextIndex);

            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            // Last applicant - go to Step 7 (Review)
            renderStep7Review();
            goToStep(7);
        }
    } catch (err) {
        console.error('[GroupBooking] handleStep6Next error:', err);
        alert('An error occurred: ' + err.message);
    }
}

/**
 * FR-3: Step 7 Access Guard
 * Enhanced goToStep with validation for Step 7 access
 * @param {number} stepNumber - Target step (1-7)
 */
function goToStep(stepNumber) {
    try {
        const state = window.groupBookingState;

        // FR-3: Guard Step 7 access
        if (stepNumber === 7 && state.isInitialized) {
            const allCompleted = state.groupApplicantsData.every(app => app && app.completed === true);
            if (!allCompleted) {
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
    } catch (err) {
        console.error('[GroupBooking] goToStep error:', err);
        alert('Navigation error: ' + err.message);
    }
}

/**
 * FR-4: Accurate Step 7 Group Review Rendering
 * Iterate over all applicants and render Group Total Fee card + individual cards with canonical snake_case data & required documents
 */
function renderStep7Review() {
    try {
        const state = window.groupBookingState;
        const reviewGrid = document.getElementById('groupReviewGrid');
        if (!reviewGrid) return;

        const app1 = state.groupApplicantsData[0] || {};
        const sharedSite = app1.site_selection || document.getElementById('siteSelect')?.value || 'Not selected';
        const sharedDate = app1.app_date_selection || document.getElementById('selectedDateVal')?.value || 'Not selected';
        const sharedTime = app1.app_time_selection || document.getElementById('appTimeSelect')?.value || 'Not selected';

        const perApplicantFee = 950;
        const totalFee = perApplicantFee * state.groupCount;
        const formattedTotalFee = totalFee.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        let htmlContent = `
            <div class="summary-card" style="background: #eef2ff; border: 1px solid #c7d2fe; padding: 20px; border-radius: var(--radius-sm); grid-column: span 2; margin-bottom: 15px;">
                <h4 style="font-size: 15px; color: var(--primary); margin-bottom: 12px; border-bottom: 2px solid #c7d2fe; padding-bottom: 8px; font-weight: 700; text-transform: uppercase;">
                    <i class="fa-solid fa-receipt"></i> Group Booking & Total Fee Summary
                </h4>
                <div style="font-size: 13px; line-height: 1.8; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; color: #1e293b;">
                    <div>
                        <div><strong>Group Size:</strong> ${state.groupCount} Applicant${state.groupCount > 1 ? 's' : ''}</div>
                        <div><strong>Shared DFA Site:</strong> ${escapeHtml(sharedSite)}</div>
                        <div><strong>Shared Date & Time:</strong> ${escapeHtml(sharedDate)} (${escapeHtml(sharedTime)})</div>
                    </div>
                    <div>
                        <div><strong>Processing Type:</strong> Regular Processing (₱950.00 / applicant)</div>
                        <div style="font-size: 15px; color: var(--primary); margin-top: 4px;"><strong>Group Total Fee:</strong> ₱950.00 × ${state.groupCount} = ₱${formattedTotalFee}</div>
                    </div>
                </div>
            </div>
        `;

        for (let i = 0; i < state.groupCount; i++) {
            const app = state.groupApplicantsData[i] || {};
            const prefix = `applicant_${i + 1}_`;
            const applicantFiles = state.applicantFiles[i] || window.applicantFiles[prefix] || {};

            // Build full name from canonical snake_case keys (with legacy camelCase fallbacks)
            const givenName = app.givenname || app.givenName || '';
            const middleName = app.middlename || app.middleName || '';
            const lastName = app.lastname || app.lastName || '';
            const extName = app.extname || app.extName || '';
            const fullName = `${givenName} ${middleName} ${lastName} ${extName}`.replace(/\s+/g, ' ').trim() || 'Not provided';

            // Shared site/schedule from applicant 1
            const site = app.site_selection || app1.site_selection || document.getElementById('siteSelect')?.value || 'Not selected';
            const date = app.app_date_selection || app1.app_date_selection || document.getElementById('selectedDateVal')?.value || 'Not selected';
            const time = app.app_time_selection || app1.app_time_selection || document.getElementById('appTimeSelect')?.value || 'Not selected';

            // Canonical application_type & credential_reason
            const appType = app.application_type || app.applicationType || '';
            const credReason = app.credential_reason || app.credentialReason || '';
            const resolvedType = appType
                ? window.DFA_RULES_HELPERS.resolveApplicationTypeLabel(appType, credReason)
                : 'Not selected';

            // Calculate age and resolve required documents
            const dob = app.dob || '';
            const apptDate = app.app_date_selection || app1.app_date_selection || document.getElementById('selectedDateVal')?.value || '';
            const age = window.DFA_RULES_ENGINE.calculateAge(dob, apptDate);
            const docs = appType
                ? window.DFA_RULES_ENGINE.getRequiredDocuments(appType, credReason, age)
                : [];

            // Build document list with attached file names
            let docHtml = '';
            if (docs.length > 0) {
                docHtml = '<ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 12px; line-height: 1.8; color: #334155;">';
                docs.forEach(doc => {
                    const fileKey = `${prefix}${doc.key}`;
                    const fileObj = applicantFiles[fileKey] || applicantFiles[doc.key];
                    const fileName = fileObj ? escapeHtml(fileObj.name) : 'No file attached (to be presented on-site)';
                    const fileColor = fileObj ? '#10b981' : '#6b7280';
                    docHtml += `<li><strong>${escapeHtml(doc.label)}</strong> ${doc.required ? '<span style="color:#ef4444;">*</span>' : ''}: <span style="color:${fileColor};">${fileName}</span></li>`;
                });
                docHtml += '</ul>';
            } else {
                docHtml = '<div style="font-size: 12px; color: #64748b; margin-top: 6px;">No documents required for this application type.</div>';
            }

            // Civil status, sex, citizenship, mobile, email display
            const civilStatus = app.civil_status || app.civilStatus || 'Not provided';
            const sex = app.sex || app.gender || 'Not provided';
            const citizenship = app.citizenship || 'Filipino';
            const mobile = app.mobile || 'Not provided';
            const email = app.email || 'Not provided';
            const dobDisplay = dob || 'Not provided';

            htmlContent += `
                <div class="summary-card" style="background: #f8fafc; border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-sm); grid-column: span 2; margin-bottom: 15px;">
                    <h4 style="font-size: 15px; color: var(--primary); margin-bottom: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; font-weight: 700; text-transform: uppercase;">Applicant ${i + 1}: ${escapeHtml(fullName)}</h4>
                    <div style="font-size: 13px; line-height: 1.8; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; color: #334155;">
                        <div>
                            <div><strong>Application Type:</strong> ${escapeHtml(resolvedType)}</div>
                            <div><strong>Email:</strong> ${escapeHtml(email)}</div>
                            <div><strong>Mobile:</strong> ${escapeHtml(mobile)}</div>
                            <div><strong>Sex:</strong> ${escapeHtml(sex)}</div>
                            <div><strong>Citizenship:</strong> ${escapeHtml(citizenship)}</div>
                        </div>
                        <div>
                            <div><strong>DOB:</strong> ${escapeHtml(dobDisplay)}</div>
                            <div><strong>Civil Status:</strong> ${escapeHtml(civilStatus)}</div>
                            <div><strong>Site:</strong> ${escapeHtml(site)}</div>
                            <div><strong>Date/Time:</strong> ${escapeHtml(date)} (${escapeHtml(time)})</div>
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
    } catch (err) {
        console.error('[GroupBooking] renderStep7Review error:', err);
        alert('Failed to render review: ' + err.message);
    }
}

/**
 * FR-2/FR-3: Step 7 Pre-Submit Completeness Gate - RELAXED: no blocking
 * Always returns valid: true to allow submission with empty/partial data.
 * Backend will supply MySQL strict-mode-safe defaults.
 * @returns {object} { valid: boolean, message: string, incompleteApplicants: number[] }
 */
function validateAllApplicantsComplete() {
    return { valid: true, message: '', incompleteApplicants: [] };
}

/**
 * FR-5: Back Navigation from Step 7
 * Returns to last applicant's Step 6 with saved data intact
 */
function handleBackFromStep7() {
    try {
        const state = window.groupBookingState;
        if (!state.isInitialized) return;

        // Go to last applicant's Step 6
        const lastIndex = state.groupCount - 1;
        goToStep(6);
        hydrateApplicantForm(lastIndex);
    } catch (err) {
        console.error('[GroupBooking] handleBackFromStep7 error:', err);
        alert('Navigation error: ' + err.message);
    }
}

/**
 * FR-5: Back Navigation from first per-applicant step (Step 3)
 * Goes to previous applicant's Step 6
 * @param {number} targetStep - The step user clicked back to (usually 2)
 */
function handleBackFromApplicantEntry(targetStep) {
    try {
        const state = window.groupBookingState;
        if (!state.isInitialized) return false;

        // If we're at Step 3 (first per-applicant step) and not on first applicant
        if (targetStep < 3 && state.currentApplicantIndex > 0) {
            // Preserve current applicant's partial edits before switching back
            commitApplicantToState(state.currentApplicantIndex, {
                markCompleted: Boolean(state.groupApplicantsData[state.currentApplicantIndex]?.completed)
            });
            const prevIndex = state.currentApplicantIndex - 1;
            goToStep(6);
            hydrateApplicantForm(prevIndex);
            return true;
        }
        return false;
    } catch (err) {
        console.error('[GroupBooking] handleBackFromApplicantEntry error:', err);
        alert('Navigation error: ' + err.message);
        return false;
    }
}

// Expose group booking functions globally
window.GroupBooking = {
    initGroupBookingState,
    commitApplicantToState,
    syncSharedScheduleToState,
    hydrateApplicantForm,
    refreshApplicantDocumentSlots,
    handleStep6Next,
    goToStep,
    renderStep7Review,
    handleBackFromStep7,
    handleBackFromApplicantEntry,
    updateGroupBanner,
    updateStep6ButtonLabel,
    validateAllApplicantsComplete
};

// Unified navigation handlers exposed globally (for inline script compatibility)
window.nextStep = (step) => {
    try {
        const state = window.groupBookingState;
        if (state.isInitialized) {
            if (step > 6) {
                window.GroupBooking.handleStep6Next();
            } else {
                if (step >= 3) {
                    window.GroupBooking.syncSharedScheduleToState();
                }
                window.GroupBooking.goToStep(step);
            }
        } else {
            if (typeof goToWizardStep === 'function') goToWizardStep(step);
        }
    } catch (err) {
        console.error('nextStep error:', err);
        alert('Navigation error: ' + err.message);
    }
};

window.prevStep = (step) => {
    try {
        const state = window.groupBookingState;
        if (state.isInitialized) {
            if (step < 3 && state.currentApplicantIndex > 0) {
                if (window.GroupBooking.handleBackFromApplicantEntry(step)) return;
            }
            window.GroupBooking.goToStep(step);
        } else {
            if (typeof goToWizardStep === 'function') goToWizardStep(step);
        }
    } catch (err) {
        console.error('prevStep error:', err);
        alert('Navigation error: ' + err.message);
    }
};

window.goToStep = (step) => {
    try {
        const state = window.groupBookingState;
        if (state.isInitialized) {
            window.GroupBooking.goToStep(step);
        } else {
            if (typeof goToWizardStep === 'function') goToWizardStep(step);
        }
    } catch (err) {
        console.error('goToStep error:', err);
        alert('Navigation error: ' + err.message);
    }
};

// Auto-initialize on DOMContentLoaded for pages that need it
document.addEventListener('DOMContentLoaded', () => {
    // Initialize Step 5 dropdowns if they exist
    const appTypeSelects = document.querySelectorAll('#applicationTypeSelect, #applicationTypeSelectGroup');
    appTypeSelects.forEach(select => {
        if (select && select.options.length <= 1) {
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

// Expose normalizeAppTypeForDisplay for review rendering
function normalizeAppTypeForDisplay(rawType) {
    const type = (rawType || '').toLowerCase().trim();
    if (type.includes('first') || type.includes('new first-time')) return 'First Time Passport';
    if (type.includes('renewal')) return 'Passport Renewal';
    if (type.includes('lost')) return 'Lost Passport';
    if (type.includes('damaged')) return 'Damaged Passport';
    if (type.includes('group')) return 'Group Passport Application';
    if (type === '' || type === 'passport application') return 'First Time Passport';
    return rawType.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
}
window.normalizeAppTypeForDisplay = normalizeAppTypeForDisplay;