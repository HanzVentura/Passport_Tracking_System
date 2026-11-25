        let currentAppData = null;
        const steps = ['Form Submitted', 'Biometrics Received', 'Under Consular Review', 'In Printing', 'Dispatched'];

        /**
         * Normalize application type for member-facing display (Title Case)
         * Converts legacy ALL CAPS DB values to Title Case
         * @param {string} rawType - Raw type from database
         * @returns {string} Normalized Title Case display value
         */
        function normalizeAppTypeForDisplay(rawType) {
            const type = (rawType || '').toLowerCase().trim();
            if (type.includes('first') || type.includes('new first-time')) return 'First Time Passport';
            if (type.includes('renewal')) return 'Passport Renewal';
            if (type.includes('lost')) return 'Lost Passport';
            if (type.includes('damaged')) return 'Damaged Passport';
            if (type.includes('group')) return 'Group Passport Application';
            if (type === '' || type === 'passport application') return 'First Time Passport';
            // Already Title Case or unknown - capitalize first letter of each word
            return rawType.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join(' ');
        }

        const dfaOfficeData = {
            "ANGELES": { name: "DFA Regional Consular Office â€“ Pampanga (Angeles)", address: "3F MarQuee Mall, Pulung Maragul, Angeles City" },
            "ANTIPOLO": { name: "DFA Regional Consular Office â€“ Antipolo", address: "3F SM Cherry Foodarama, Marikina-Infanta Highway, Antipolo City, Rizal" },
            "ANTIQUE": { name: "DFA Regional Consular Office â€“ Antique", address: "CityMall Antique, National Road, San Jose de Buenavista, Antique" },
            "BACOLOD": { name: "DFA Regional Consular Office â€“ Bacolod", address: "3rd Level Robinsons Place Bacolod, Brgy. Mandalangan, Bacolod City" },
            "BAGUIO": { name: "DFA Regional Consular Office â€“ Baguio", address: "SM City Baguio Upper Basement, Luneta Hill, Upper Session Road, Baguio City" },
            "BALANGA": { name: "DFA Regional Consular Office â€“ Balanga", address: "The Bunker Building, Capitol Compound, Balanga City, Bataan" },
            "BUTUAN": { name: "DFA Regional Consular Office â€“ Butuan", address: "3rd Level Robinsons Place Butuan, J.C. Aquino Ave., Butuan City" },
            "CAGAYAN DE ORO": { name: "DFA Regional Consular Office â€“ Cagayan de Oro", address: "3F Centrio Mall, cor. Corrales and Recto Sts., Cagayan de Oro City" },
            "CALASIAO": { name: "DFA Regional Consular Office â€“ Calasiao", address: "2F Robinsons Place-Pangasinan, Brgy. San Miguel, Calasiao, Pangasinan" },
            "CANDON": { name: "DFA Regional Consular Office â€“ Candon", address: "Candon City Arena, Candon City, Ilocos Sur" },
            "CEBU": { name: "DFA Regional Consular Office â€“ Cebu", address: "4th Level Pacific Mall-Metro Mandaue, UN Ave. cor. MC Briones St., Mandaue City" },
            "CLARIN": { name: "DFA Regional Consular Office â€“ Clarin", address: "Clarin Town Center, Clarin, Misamis Occidental" },
            "DFA MANILA": { name: "DFA Manila (ASEANA)", address: "ASEANA Business Park, Bradco Avenue corner Macapagal Boulevard, ParaÃ±aque City" },
            "DFA NCR CENTRAL": { name: "DFA NCR Central", address: "Lingkod Pinoy Center, Robinsons Galleria, West Lane EDSA cor. Ortigas Ave., Quezon City" },
            "DFA NCR EAST": { name: "DFA NCR East (Megamall)", address: "7F Building C, SM Megamall, EDSA cor. J. Vargas Ave., Mandaluyong City" },
            "DFA NCR NORTH": { name: "DFA NCR North (Novaliches)", address: "Quirino Highway, Novaliches, Quezon City (across SM City Fairview)" },
            "DFA NCR NORTHEAST": { name: "DFA NCR Northeast (Ali Mall)", address: "Level 2, Ali Mall Government Center, Cubao, Quezon City" },
            "DFA NCR SOUTH": { name: "DFA NCR South (Alabang)", address: "4th Floor, Metro Alabang Town Center, Muntinlupa City" },
            "DFA NCR WEST": { name: "DFA NCR West (SM Manila)", address: "5th Floor, SM City Manila, Concepcion St., Manila" },
            "DASMARIÃ‘AS": { name: "DFA Regional Consular Office â€“ DasmariÃ±as", address: "SM City DasmariÃ±as, Governor's Drive, Brgy. Sampaloc 1, DasmariÃ±as City, Cavite" },
            "DAVAO": { name: "DFA Regional Consular Office â€“ Davao", address: "3F SM City-Davao Quimpo Boulevard, Ecoland Subdivision, Davao City" },
            "DUMAGUETE": { name: "DFA Regional Consular Office â€“ Dumaguete", address: "2F Robinsons Place South Road, Calidangan, Dumaguete City" },
            "GENERAL SANTOS": { name: "DFA Regional Consular Office â€“ General Santos", address: "GF Robinsons Place GenSan, Jose Catolico Sr. Ave., General Santos City" },
            "ILOCOS NORTE": { name: "DFA Regional Consular Office â€“ Ilocos Norte", address: "Robinsons Place Ilocos Norte, Brgy. San Francisco, San Nicolas, Ilocos Norte" },
            "ILOILO": { name: "DFA Regional Consular Office â€“ Iloilo", address: "3F Robinsons Place Iloilo Quezon Wing, Iloilo City" },
            "KIDAPAWAN": { name: "DFA Regional Consular Office â€“ Kidapawan", address: "People's Palace, City Hall, Kidapawan City" },
            "LA UNION": { name: "DFA Regional Consular Office â€“ La Union", address: "2nd Floor Manna Mall, Marcos Highway corner Diversion Road, San Fernando, La Union" },
            "LEGAZPI": { name: "DFA Regional Consular Office â€“ Legazpi", address: "3F Pacific Mall-Legazpi, Landco Business Park, Legazpi City" },
            "LIPA": { name: "DFA Regional Consular Office â€“ Lipa", address: "2F Robinsons Place-Lipa, JP Laurel Highway, Lipa City, Batangas" },
            "LUCENA": { name: "DFA Regional Consular Office â€“ Lucena", address: "3F Pacific Mall-Lucena, M.L. Tagarao St., Lucena City" },
            "MALOLOS": { name: "DFA Regional Consular Office â€“ Malolos", address: "3F Malolos Central Transport Terminal and Commercial Hub by Xentro Mall, Malolos City" },
            "OLONGAPO": { name: "DFA Regional Consular Office â€“ Olongapo", address: "SM City Olongapo Central, Gordon Ave., Olongapo City" },
            "PAGADIAN": { name: "DFA Regional Consular Office â€“ Pagadian", address: "C3 Mall, Pagadian City, Zamboanga del Sur" },
            "PAMPANGA": { name: "DFA Regional Consular Office â€“ Pampanga Starmills", address: "2F Robinsons StarMills, City of San Fernando, Pampanga" },
            "PANIQUI, TARLAC": { name: "DFA Regional Consular Office â€“ Paniqui", address: "Waltermart Paniqui, Tarlac" },
            "PUERTO PRINCESA": { name: "DFA Regional Consular Office â€“ Puerto Princesa", address: "2nd Level Robinsons Place Palawan, National Highway, Puerto Princesa City" },
            "SAN PABLO": { name: "DFA Regional Consular Office â€“ San Pablo", address: "2F SM City San Pablo, Maharlika Highway, San Pablo City, Laguna" },
            "SANTIAGO, ISABELA": { name: "DFA Regional Consular Office â€“ Santiago", address: "Robinsons Place Santiago, Maharlika Highway, Santiago City, Isabela" },
            "TACLOBAN": { name: "DFA Regional Consular Office â€“ Tacloban", address: "3rd Level, Robinsons North Tacloban, Brgy. Abucay, Tacloban City" },
            "TAGBILARAN": { name: "DFA Regional Consular Office â€“ Tagbilaran", address: "Alturas Mall, Tagbilaran City, Bohol" },
            "TAGUM": { name: "DFA Regional Consular Office â€“ Tagum", address: "Robinsons Place Tagum, Tagum City, Davao del Norte" },
            "TUGUEGARAO": { name: "DFA Regional Consular Office â€“ TugueGarao", address: "Regional Government Center, Carig Sur, TugueGarao City" },
            "ZAMBOANGA": { name: "DFA Regional Consular Office â€“ Zamboanga", address: "Go-Velayoor Building, Vet. Ave., Zamboanga City" }
        };
       
        document.getElementById('searchForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const appId = document.getElementById('appIdInput').value.trim();
            const msgEl = document.getElementById('formMessage');
            msgEl.textContent = '';
           
            if (!appId) return;
            try {
                const response = await fetch(`../api/get-application.php?applicationId=${encodeURIComponent(appId)}`);
                const result = await response.json();
                if (response.ok && result.success && result.application) {
                    currentAppData = result.application;
                    const app = currentAppData;
                   
                    document.getElementById('resAppId').textContent = app.application_id || app.applicationId || appId;
                    const rawAppType = app.application_type || app.applicationType || 'New First-Time Application';
                    document.getElementById('resType').textContent = normalizeAppTypeForDisplay(rawAppType);
                    document.getElementById('resStatus').textContent = app.status || 'Form Submitted';
                    document.getElementById('resDate').textContent = (app.createdAt || app.created_at) ? new Date(app.createdAt || app.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '---';
                    document.getElementById('resEstDate').textContent = `Est. Completion: ${app.estimatedCompletionDate || '---'}`;
                   
                    // Update Timeline steps
                    const currentStatusIdx = steps.indexOf(app.status);
                    steps.forEach((stepName, idx) => {
                        const stepEl = document.getElementById(`step${idx + 1}`);
                        if (stepEl) {
                            if (currentStatusIdx !== -1 && idx <= currentStatusIdx) {
                                stepEl.classList.add('completed');
                                stepEl.querySelector('i').className = 'fa-solid fa-circle-check';
                            } else {
                                stepEl.classList.remove('completed');
                                stepEl.querySelector('i').className = 'fa-regular fa-circle';
                            }
                        }
                    });

                    // Dynamic Post-Dispatch Handling for Public Tracker
                    const timelineContainer = document.getElementById('timelineContainer');
                    const postDispatchContainer = document.getElementById('postDispatchContainer');
                    const cardTitle = document.getElementById('statusCardTitle');

                    if (app.status === 'Dispatched') {
                        cardTitle.textContent = 'Post-Dispatch Instructions';
                        timelineContainer.style.display = 'none';
                        postDispatchContainer.style.display = 'block';

                        const receivingOpt = app.receiving_option || app.receivingOption;
                        if (receivingOpt === 'Delivery') {
                            postDispatchContainer.innerHTML = `
                                <strong style="display: block; margin-bottom: 6px; color: var(--primary);">Courier Dispatch Notice:</strong>
                                <p style="margin-bottom: 12px; color: var(--text-muted);">Your passport has been successfully dispatched via courier to your registered delivery address.</p>
                                <a href="delivery-tracker.html?id=${app.application_id || appId}" style="display: inline-flex; align-items: center; gap: 6px; background: var(--primary); color: #fff; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none;">
                                    <i class="fa-solid fa-truck-fast"></i> Click Here to Track Delivery
                                </a>
                            `;
                        } else {
                            postDispatchContainer.innerHTML = `
                                <strong style="display: block; margin-bottom: 6px; color: var(--primary);">Ready for In-Person Pickup:</strong>
                                <p style="color: var(--text-muted); line-height: 1.5;">You can now claim your passport at your designated consular office during regular working days (Monday to Friday, 8:00 AM to 5:00 PM). Please bring a valid ID and your official receipt.</p>
                            `;
                        }
                    } else {
                        cardTitle.textContent = 'Application Status';
                        timelineContainer.style.display = 'block';
                        postDispatchContainer.style.display = 'none';
                    }

                    document.getElementById('placeholderState').style.display = 'none';
                    document.getElementById('resultsContainer').style.display = 'grid';
                } else {
                    msgEl.textContent = result.message || 'Application ID not found. Please check your number.';
                    msgEl.className = 'message error';
                }
            } catch (err) {
                msgEl.textContent = 'An error occurred while fetching application status.';
                msgEl.className = 'message error';
            }
        });

        function downloadApplicationSummary() {
            if (!currentAppData) return;
            const app = currentAppData;
            const appId = app.application_id || app.applicationId || 'N/A';
            const rawAppType = app.application_type || app.applicationType || 'New First-Time Application';
            const appType = normalizeAppTypeForDisplay(rawAppType);
            const status = app.status || 'Form Submitted';
            const payment = 'Verified (Online)';
            const subDate = (app.createdAt || app.created_at) ? new Date(app.createdAt || app.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'N/A';
            const rawAppDate = app.appointmentDate || app.appointment_date;
            const appDate = rawAppDate ? new Date(`${rawAppDate}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Not Scheduled';
            const appTime = app.appointmentTime || app.appointment_time || 'N/A';
           
            const rawLoc = app.appointmentLocation || app.appointment_location || 'DFA Consular Office';
            const office = dfaOfficeData[rawLoc];
            const appLoc = office ? `${office.name} - ${office.address}` : rawLoc;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Passport Application Summary - ${appId}</title>
                    <style>
                        body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; padding: 40px; color: #0f172a; }
                        .header { text-align: center; border-bottom: 2px solid #4f46e5; padding-bottom: 20px; margin-bottom: 30px; }
                        .header h1 { color: #4f46e5; font-size: 24px; margin-bottom: 5px; }
                        .header p { color: #64748b; font-size: 14px; }
                        .details-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .details-table th, .details-table td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
                        .details-table th { width: 35%; color: #64748b; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em; }
                        .details-table td { font-weight: 700; color: #0f172a; }
                        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Passport Tracking System</h1>
                        <p>Official Application Summary Report</p>
                    </div>
                    <table class="details-table">
                        <tr><th>Application ID</th><td>${appId}</td></tr>
                        <tr><th>Application Type</th><td>${appType}</td></tr>
                        <tr><th>Current Status</th><td>${status}</td></tr>
                        <tr><th>Online Payment</th><td>${payment}</td></tr>
                        <tr><th>Submitted Date</th><td>${subDate}</td></tr>
                        <tr><th>Appointment Date</th><td>${appDate}</td></tr>
                        <tr><th>Appointment Time</th><td>${appTime}</td></tr>
                        <tr><th>Appointment Location</th><td>${appLoc}</td></tr>
                    </table>
                    <div class="footer">
                        <p>Generated securely via Passport Tracking System Public Tracker.</p>
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                        }
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
