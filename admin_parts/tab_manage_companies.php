<div class="admin-section">
    <div class="admin-section-header">
        <h2>Hantera Företag (Alla)</h2>
        <p>Redigera uppgifter, hantera tjänster och aktivera/inaktivera befintliga företag.</p>
    </div>
    <div class="admin-section-body">
        <div class="company-grid">
            <?php foreach ($companies as $company_id => $company): ?>
                <div class="company-card">
                    <div class="flex-grow">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <strong><?= htmlspecialchars($company['company_name']) ?></strong><br>
                                <small><?= htmlspecialchars($company['contact_email']) ?></small><br>
                                <small>📍 <?= htmlspecialchars($company['city'] ?? 'Okänd ort') ?></small>
                            </div>
                            <span class="status-badge status-<?= htmlspecialchars($company['status']) ?>"><?= ucfirst(htmlspecialchars($company['status'])) ?></span>
                        </div>

                        <div class="mt-2 mb-4">
                            <h4 class="text-sm font-semibold mb-2 text-gray-600">Aktiva tjänster:</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                $current_services = $company_service_names[$company_id] ?? [];
                                if (!empty($current_services)):
                                    foreach ($current_services as $service_name): ?>
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-1 rounded-full">
                                            <?= htmlspecialchars($service_name) ?>
                                        </span>
                                    <?php endforeach;
                                else: ?>
                                    <span class="text-xs text-gray-500">Inga tjänster valda.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t flex gap-2 flex-wrap">
                        <form action="mainadmin.php" method="POST" class="w-full flex-grow">
                            <input type="hidden" name="active_tab" value="companies">
                            <input type="hidden" name="company_id" value="<?= $company_id ?>">
                            <?php if ($company['status'] === 'inactive'): ?>
                                <input type="hidden" name="new_status" value="active">
                                <button type="submit" name="update_company_status" class="w-full p-2 text-sm font-bold text-white bg-green-500 rounded-md hover:bg-green-600">Aktivera</button>
                            <?php else: ?>
                                <input type="hidden" name="new_status" value="inactive">
                                <button type="submit" name="update_company_status" class="w-full p-2 text-sm font-bold text-white bg-red-500 rounded-md hover:bg-red-600">Inaktivera</button>
                            <?php endif; ?>
                        </form>
                        <button type="button" class="w-full flex-grow p-2 text-sm font-bold text-white bg-gray-500 rounded-md hover:bg-gray-600 open-edit-modal-btn" data-company-id="<?= $company_id ?>">Redigera</button>
                        <button type="button" class="w-full flex-grow p-2 text-sm font-bold text-white bg-indigo-500 rounded-md hover:bg-indigo-600 edit-company-services-btn" data-company-id="<?= $company_id ?>" data-company-name="<?= htmlspecialchars($company['company_name']) ?>">Hantera tjänster</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const companyServicesModal = document.getElementById('companyServicesModal');
    const allServices = <?= json_encode($all_services) ?>;
    const companiesData = <?= json_encode($companies) ?>;
    const companyServiceLinks = <?= json_encode($company_service_links) ?>;

    document.querySelectorAll('.edit-company-services-btn').forEach(button => {
        button.addEventListener('click', function() {
            const companyId = this.dataset.companyId;
            const companyName = this.dataset.companyName;
            const linkedServices = companyServiceLinks[companyId] || [];

            document.getElementById('servicesModalCompanyName').textContent = `Hantera tjänster för: ${companyName}`;
            document.getElementById('services_company_id').value = companyId;

            // Visa tydligt valda tjänster i tagg-format överst i modalen
            let selectedServicesDisplay = document.getElementById('selectedServicesDisplay');
            if (!selectedServicesDisplay) {
                selectedServicesDisplay = document.createElement('div');
                selectedServicesDisplay.id = 'selectedServicesDisplay';
                selectedServicesDisplay.className = 'mb-3';
                document.getElementById('modalCompanyServiceList').parentNode.insertBefore(selectedServicesDisplay, document.getElementById('modalCompanyServiceList'));
            }
            const selectedNames = allServices
                .filter(service => linkedServices.includes(String(service.id)))
                .map(service => `<span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-1 rounded-full mr-1 mb-1">${service.name}</span>`);
            selectedServicesDisplay.innerHTML = selectedNames.length > 0 ? selectedNames.join('') : '<span class="text-xs text-gray-500">Inga tjänster valda.</span>';

            // Visa checklistan
            const serviceList = document.getElementById('modalCompanyServiceList');
            serviceList.innerHTML = '';
            allServices.forEach(service => {
                const isChecked = linkedServices.includes(String(service.id));
                serviceList.innerHTML += `<li><label class="flex items-center"><input type="checkbox" name="services[]" value="${service.id}" ${isChecked ? 'checked' : ''} class="mr-2"> ${service.name}</label></li>`;
            });

            // Dynamisk uppdatering av taggar när du klickar i/ur tjänster
            serviceList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const checkedIds = Array.from(serviceList.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
                    const updatedNames = allServices
                        .filter(service => checkedIds.includes(String(service.id)))
                        .map(service => `<span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-1 rounded-full mr-1 mb-1">${service.name}</span>`);
                    selectedServicesDisplay.innerHTML = updatedNames.length > 0 ? updatedNames.join('') : '<span class="text-xs text-gray-500">Inga tjänster valda.</span>';
                });
            });

            companyServicesModal.style.display = 'block';
        });
    });

    // Gemensam stängningslogik (om den inte redan finns i mainadmin.php)
    document.querySelectorAll('.close-button').forEach(btn => {
        btn.onclick = function() { this.closest('.modal').style.display = 'none'; }
    });
    window.onclick = function(event) {
        if (event.target.classList && event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
});
</script>
