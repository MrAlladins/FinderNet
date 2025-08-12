<?php
// Visa fel under utveckling
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inkludera databasanslutningen
require_once 'dbconn.php';
session_start();

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Logik för att komma ihåg aktiv flik efter en POST
$active_tab = 'premium_projects'; // Standardflik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['active_tab'])) {
    $valid_tabs = ['pending', 'companies', 'homepage', 'partners', 'settings', 'premium_projects'];
    if (in_array($_POST['active_tab'], $valid_tabs)) {
        $active_tab = $_POST['active_tab'];
    }
}

//======================================================================
// HANTERA SPARANDE AV ÄNDRINGAR (FORMULÄR-POST)
//======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if (isset($_POST['save_and_approve'])) {
            $company_id = (int)$_POST['company_id'];
            $company_name = trim($_POST['company_name']);
            $contact_email = trim($_POST['contact_email']);
            $contact_phone = trim($_POST['contact_phone']);
            $credits = (int)$_POST['credits'];
            $address = trim($_POST['address']);
            $postal_code = trim($_POST['postal_code']);
            $city = trim($_POST['city']);
            $selected_services = $_POST['services'] ?? [];

            $sql_update = "UPDATE companies SET
                                company_name = ?, contact_email = ?, contact_phone = ?, credits = ?,
                                address = ?, postal_code = ?, city = ?, status = 'active'
                               WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$company_name, $contact_email, $contact_phone, $credits, $address, $postal_code, $city, $company_id]);

            $stmt_delete_services = $pdo->prepare("DELETE FROM company_services WHERE company_id = ?");
            $stmt_delete_services->execute([$company_id]);
            if (!empty($selected_services)) {
                $stmt_insert_service = $pdo->prepare("INSERT INTO company_services (company_id, service_id) VALUES (?, ?)");
                foreach ($selected_services as $service_id) {
                    $stmt_insert_service->execute([$company_id, $service_id]);
                }
            }
            $success_message = "Företaget '".htmlspecialchars($company_name)."' har uppdaterats och godkänts!";
        }
        
        if (isset($_POST['save_edited_company'])) {
            $company_id = (int)$_POST['company_id'];
            $company_name = trim($_POST['company_name']);
            $contact_email = trim($_POST['contact_email']);
            $contact_phone = trim($_POST['contact_phone']);
            $credits = (int)$_POST['credits'];
            $address = trim($_POST['address']);
            $postal_code = trim($_POST['postal_code']);
            $city = trim($_POST['city']);

            $sql_update = "UPDATE companies SET
                                company_name = ?, contact_email = ?, contact_phone = ?, credits = ?,
                                address = ?, postal_code = ?, city = ?
                               WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$company_name, $contact_email, $contact_phone, $credits, $address, $postal_code, $city, $company_id]);
            $success_message = "Företaget '".htmlspecialchars($company_name)."' har uppdaterats!";
        }
        
        if (isset($_POST['save_service_settings'])) {
            if (!isset($_POST['visible_services'])) {
                $error_message = "Inga tjänster valdes för synlighet.";
            } else {
                $visible_service_ids = $_POST['visible_services'] ?? [];
                $stmt_update_all_visibility = $pdo->prepare("UPDATE services SET is_visible = 0");
                $stmt_update_all_visibility->execute();
                if (!empty($visible_service_ids)) {
                    $placeholders = implode(',', array_fill(0, count($visible_service_ids), '?'));
                    $stmt_update = $pdo->prepare("UPDATE services SET is_visible = 1 WHERE id IN ($placeholders)");
                    $stmt_update->execute($visible_service_ids);
                }
                $success_message = "Inställningarna för tjänster har uppdaterats!";
            }
        }
        if (isset($_POST['save_company_services'])) {
            $company_id = (int)$_POST['company_id'];
            $linked_services = $_POST['services'] ?? [];
            $stmt_delete = $pdo->prepare("DELETE FROM company_services WHERE company_id = ?");
            $stmt_delete->execute([$company_id]);
            if (!empty($linked_services)) {
                $stmt_insert = $pdo->prepare("INSERT INTO company_services (company_id, service_id) VALUES (?, ?)");
                foreach ($linked_services as $service_id) {
                    $stmt_insert->execute([$company_id, $service_id]);
                }
            }
            $success_message = "Företagets tjänster har uppdaterats!";
        }
        
        if (isset($_POST['add_partner'])) { /* ... */ }
        if (isset($_POST['update_partner_price'])) { /* ... */ }
        if (isset($_POST['save_partner_services'])) { /* ... */ }
        if (isset($_POST['update_company_status'])) {
            $company_id = (int)$_POST['company_id'];
            $new_status = in_array($_POST['new_status'], ['active', 'inactive']) ? $_POST['new_status'] : 'pending';
            $stmt = $pdo->prepare("UPDATE companies SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $company_id]);
            $success_message = "Företagets status har uppdaterats!";
        }
        if (isset($_POST['update_sms_status'])) { /* ... */ }
        if (isset($_POST['update_credit_status'])) { /* ... */ }
        if (isset($_POST['update_project_manager_email'])) { /* ... */ }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Ett databasfel inträffade: " . $e->getMessage();
    }
}
//======================================================================
// HÄMTA ALL DATA FÖR ATT VISA SIDAN
//======================================================================
try {
    $all_services = $pdo->query("SELECT id, name, is_visible FROM services ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $partners = $pdo->query("SELECT id, name, partner_key, price_per_visit FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmt_partner_services = $pdo->query("SELECT partner_id, service_id FROM partner_services");
    $partner_service_links = $stmt_partner_services->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

    $sql_companies = "SELECT * FROM companies WHERE status != 'pending' ORDER BY created_at DESC";
    $companies = $pdo->query($sql_companies)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

    $sql_pending_companies = "SELECT * FROM companies WHERE status = 'pending' ORDER BY created_at ASC";
    $pending_companies = $pdo->query($sql_pending_companies)->fetchAll(PDO::FETCH_ASSOC);
    $pending_count = count($pending_companies);

    $stmt_company_services = $pdo->query("SELECT company_id, service_id FROM company_services");
    $company_service_links = $stmt_company_services->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
    
    $services_by_id = [];
    foreach ($all_services as $service) {
        $services_by_id[$service['id']] = $service['name'];
    }
    $company_service_names = [];
    foreach ($company_service_links as $company_id => $service_ids) {
        $company_service_names[$company_id] = [];
        foreach ($service_ids as $service_id) {
            if (isset($services_by_id[$service_id])) {
                $company_service_names[$company_id][] = $services_by_id[$service_id];
            }
        }
    }
    
    $settings_stmt = $pdo->prepare("SELECT setting_name, setting_value FROM settings");
    $settings_stmt->execute();
    $all_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $current_credit_status = $all_settings['credit_system_enabled'] ?? 'paused';
    $current_sms_status = $all_settings['sms_enabled'] ?? 'paused';
    $project_manager_email = $all_settings['project_manager_email'] ?? '';

    $sql_premium_projects = "SELECT pp.id, pp.status, pp.request_id, cr.customer_name, cr.created_at, s.name as service_name FROM premium_projects pp JOIN customer_requests cr ON pp.request_id = cr.id JOIN services s ON cr.service_id = s.id ORDER BY cr.created_at DESC";
    $premium_projects = $pdo->query($sql_premium_projects)->fetchAll(PDO::FETCH_ASSOC);
    $premium_count = count($premium_projects);

} catch (PDOException $e) {
    die("Kunde inte hämta data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Huvudadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        body { background-color: #f9fafb; font-family: sans-serif; color: #111827; }
        .container { max-width: 1400px; margin: 40px auto; padding: 1em; }
        .admin-section { background-color: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .admin-section-header { padding: 20px 25px; border-bottom: 1px solid #e5e7eb; }
        .admin-section-header h2 { margin: 0; font-size: 1.5rem; }
        .admin-section-header p { margin: 5px 0 0 0; color: #6b7280; }
        .admin-section-body { padding: 25px; }
        .button { display: inline-block; width: 100%; padding: 12px; font-size: 1em; font-weight: bold; margin-top: 20px; box-sizing: border-box; border-radius: 8px; text-align: center; cursor: pointer; border: none; }
        .company-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .company-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
        .company-info strong { font-weight: 600; }
        .company-info small { color: #6b7280; }
        .status-badge { padding: 4px 10px; border-radius: 999px; font-size: 0.8em; font-weight: 600; }
        .status-active { background-color: #d1fae5; color: #065f46; }
        .status-inactive { background-color: #fee2e2; color: #991b1b; }
        .checklist { list-style-type: none; padding: 0; display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        .partner-list { list-style: none; padding: 0; }
        .partner-list li { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f3f4f6; }
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .setting-card { background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .setting-card h3 { margin-top: 0; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 0; border: 1px solid #888; width: 90%; max-width: 800px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-header { padding: 20px 25px; background-color: #f3f4f6; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; }
        .modal-body-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .modal-footer { padding: 20px 25px; text-align: right; background-color: #f9fafb; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; }
        .close-button { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .badge { background-color: #ef4444; color: white; font-size: 0.75rem; font-weight: bold; padding: 2px 8px; border-radius: 999px; margin-left: 8px; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">

<div class="container" x-data="{ activeTab: '<?= htmlspecialchars($active_tab) ?>' }">
    <h1 class="text-4xl text-center mb-8 font-bold">Huvudadmin</h1>

    <?php if ($success_message): ?><p class="my-4 text-center text-green-700 font-bold"><?= htmlspecialchars($success_message) ?></p><?php endif; ?>
    <?php if ($error_message): ?><p class="my-4 text-center text-red-700 font-bold"><?= htmlspecialchars($error_message) ?></p><?php endif; ?>

    <div class="border-b border-gray-200 mb-8">
        <nav class="-mb-px flex flex-wrap space-x-8" aria-label="Tabs">
            <button @click="activeTab = 'pending'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'pending', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'pending' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Väntande Företag
                <?php if ($pending_count > 0): ?><span class="badge"><?= $pending_count ?></span><?php endif; ?>
            </button>
            <button @click="activeTab = 'companies'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'companies', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'companies' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Hantera Företag (Alla)</button>
            <button @click="activeTab = 'homepage'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'homepage', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'homepage' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Tjänstinställningar</button>
            <button @click="activeTab = 'partners'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'partners', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'partners' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Partners</button>
            <button @click="activeTab = 'settings'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'settings', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'settings' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Systeminställningar</button>
            <button @click="activeTab = 'premium_projects'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'premium_projects', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'premium_projects' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Premium Projekt
                <?php if ($premium_count > 0): ?><span class="badge bg-blue-500"><?= $premium_count ?></span><?php endif; ?>
            </button>
        </nav>
    </div>
    
    <div x-show="activeTab === 'pending'" x-cloak>
        <?php include 'admin_parts/tab_pending_companies.php'; ?>
    </div>
    <div x-show="activeTab === 'companies'" x-cloak>
        <?php include 'admin_parts/tab_manage_companies.php'; ?>
    </div>
    <div x-show="activeTab === 'homepage'" x-cloak>
        <?php include 'admin_parts/tab_service_settings.php'; ?>
    </div>
    <div x-show="activeTab === 'partners'" x-cloak>
        <?php include 'admin_parts/tab_partners.php'; ?>
    </div>
    <div x-show="activeTab === 'settings'" x-cloak>
        <?php include 'admin_parts/tab_system_settings.php'; ?>
    </div>
    <div x-show="activeTab === 'premium_projects'" x-cloak>
        <?php include 'admin_parts/tab_premium_projects.php'; ?>
    </div>

</div>

<div id="companyServicesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="servicesModalCompanyName"></h2>
            <span class="close-button">&times;</span>
        </div>
        <form action="mainadmin.php" method="POST">
            <input type="hidden" name="active_tab" value="companies">
            <input type="hidden" name="company_id" id="services_company_id">
            <div class="modal-body">
                <p>Välj de tjänster som detta företag ska kunna ta emot förfrågningar för:</p>
                <ul class="checklist" id="modalCompanyServiceList"></ul>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_company_services" class="button bg-indigo-600 text-white hover:bg-indigo-700">Spara ändringar</button>
            </div>
        </form>
    </div>
</div>
<div id="partnerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalPartnerName"></h2>
            <span class="close-button">&times;</span>
        </div>
        <form action="mainadmin.php" method="POST">
            <input type="hidden" name="active_tab" value="partners">
            <input type="hidden" name="partner_id" id="modalPartnerId">
            <div class="modal-body">
                <p>Välj de tjänster som denna partner ska kunna ta emot förfrågningar för:</p>
                <ul class="checklist" id="modalPartnerServiceList"></ul>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_partner_services" class="button bg-indigo-600 text-white hover:bg-indigo-700">Spara ändringar</button>
            </div>
        </form>
    </div>
</div>
<div id="pendingCompanyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="pendingModalCompanyName" class="text-xl font-bold"></h2>
            <span class="close-button">&times;</span>
        </div>
        <form action="mainadmin.php" method="POST">
            <input type="hidden" name="active_tab" value="pending">
            <input type="hidden" name="company_id" id="pending_company_id">
            <div class="modal-body">
                <div class="modal-body-grid">
                    <div>
                        <label for="modal_company_name" class="font-semibold">Företagsnamn</label>
                        <input type="text" id="modal_company_name" name="company_name" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="modal_contact_email" class="font-semibold">E-post</label>
                        <input type="email" id="modal_contact_email" name="contact_email" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="modal_contact_phone" class="font-semibold">Telefon</label>
                        <input type="text" id="modal_contact_phone" name="contact_phone" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="modal_credits" class="font-semibold">Start-credits</label>
                        <input type="number" id="modal_credits" name="credits" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="modal_address" class="font-semibold">Adress</label>
                        <input type="text" id="modal_address" name="address" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="modal_postal_code" class="font-semibold">Postnummer</label>
                        <input type="text" id="modal_postal_code" name="postal_code" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="modal_city" class="font-semibold">Stad</label>
                        <input type="text" id="modal_city" name="city" class="w-full p-2 border rounded">
                    </div>
                </div>
                <hr class="my-6">
                <h3 class="text-lg font-semibold mb-2">Valda Tjänster</h3>
                <div class="checklist" id="pendingModalServiceList"></div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_and_approve" class="px-6 py-2 font-bold text-white bg-green-500 rounded-md hover:bg-green-600">Spara & Godkänn</button>
            </div>
        </form>
    </div>
</div>
<div id="editCompanyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="editModalCompanyName" class="text-xl font-bold"></h2>
            <span class="close-button">&times;</span>
        </div>
        <form action="mainadmin.php" method="POST">
            <input type="hidden" name="active_tab" value="companies">
            <input type="hidden" name="company_id" id="edit_company_id">
            <div class="modal-body">
                <div class="modal-body-grid">
                    <div>
                        <label for="edit_company_name" class="font-semibold">Företagsnamn</label>
                        <input type="text" id="edit_company_name" name="company_name" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="edit_contact_email" class="font-semibold">E-post</label>
                        <input type="email" id="edit_contact_email" name="contact_email" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="edit_contact_phone" class="font-semibold">Telefon</label>
                        <input type="text" id="edit_contact_phone" name="contact_phone" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="edit_credits" class="font-semibold">Credits</label>
                        <input type="number" id="edit_credits" name="credits" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="edit_address" class="font-semibold">Adress</label>
                        <input type="text" id="edit_address" name="address" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="edit_postal_code" class="font-semibold">Postnummer</label>
                        <input type="text" id="edit_postal_code" name="postal_code" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label for="edit_city" class="font-semibold">Stad</label>
                        <input type="text" id="edit_city" name="city" class="w-full p-2 border rounded">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_edited_company" class="px-6 py-2 font-bold text-white bg-blue-500 rounded-md hover:bg-blue-600">Spara ändringar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const companyServicesModal = document.getElementById('companyServicesModal');
    const partnerModal = document.getElementById('partnerModal');
    const pendingCompanyModal = document.getElementById('pendingCompanyModal');
    const editCompanyModal = document.getElementById('editCompanyModal');
    
    const allServices = <?= json_encode($all_services) ?>;
    const companiesData = <?= json_encode($companies) ?>;
    const companyServiceLinks = <?= json_encode($company_service_links) ?>;
    const partnerServiceLinks = <?= json_encode($partner_service_links) ?>;

    document.querySelectorAll('.edit-company-services-btn').forEach(button => {
        button.addEventListener('click', function() {
            const companyId = this.dataset.companyId;
            const companyName = this.dataset.companyName;
            const linkedServices = companyServiceLinks[companyId] || [];
            document.getElementById('servicesModalCompanyName').textContent = `Hantera tjänster för: ${companyName}`;
            document.getElementById('services_company_id').value = companyId;
            const serviceList = document.getElementById('modalCompanyServiceList');
            serviceList.innerHTML = '';
            allServices.forEach(service => {
                // =============================================
                // START: SISTA FÖRSÖKET TILL FIX
                // =============================================
                // Denna nya rad använder en "lös" jämförelse (==) som ignorerar skillnaden mellan
                // text och siffror (t.ex. kommer 5 == "5" att vara sant). Detta är den mest
                // robusta metoden när datatyperna är osäkra.
                const isChecked = linkedServices.find(linkedId => linkedId == service.id) !== undefined;
                // =============================================
                // SLUT: SISTA FÖRSÖKET TILL FIX
                // =============================================
                serviceList.innerHTML += `<li><label class="flex items-center"><input type="checkbox" name="services[]" value="${service.id}" ${isChecked ? 'checked' : ''} class="mr-2"> ${service.name}</label></li>`;
            });
            companyServicesModal.style.display = 'block';
        });
    });

    document.querySelectorAll('.open-edit-modal-btn').forEach(button => {
        button.addEventListener('click', function() {
            const companyId = this.dataset.companyId;
            const company = companiesData[companyId];
            if (company) {
                document.getElementById('editModalCompanyName').textContent = `Redigera: ${company.company_name}`;
                document.getElementById('edit_company_id').value = companyId;
                document.getElementById('edit_company_name').value = company.company_name;
                document.getElementById('edit_contact_email').value = company.contact_email;
                document.getElementById('edit_contact_phone').value = company.contact_phone || '';
                document.getElementById('edit_credits').value = company.credits;
                document.getElementById('edit_address').value = company.address || '';
                document.getElementById('edit_postal_code').value = company.postal_code || '';
                document.getElementById('edit_city').value = company.city || '';
                editCompanyModal.style.display = 'block';
            }
        });
    });
    
    document.querySelectorAll('.edit-partner-btn').forEach(button => {
        button.addEventListener('click', function() {
            const partnerId = this.dataset.partnerId;
            const partnerName = this.dataset.partnerName;
            const linkedServices = partnerServiceLinks[partnerId] || [];
            document.getElementById('modalPartnerName').textContent = `Hantera tjänster för: ${partnerName}`;
            document.getElementById('modalPartnerId').value = partnerId;
            const serviceList = document.getElementById('modalPartnerServiceList');
            serviceList.innerHTML = '';
            allServices.forEach(service => {
                const isChecked = linkedServices.includes(String(service.id));
                serviceList.innerHTML += `<li><label><input type="checkbox" name="partner_services[]" value="${service.id}" ${isChecked ? 'checked' : ''}> ${service.name}</label></li>`;
            });
            partnerModal.style.display = 'block';
        });
    });
    
    document.querySelectorAll('.open-review-modal-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;
            const companyId = data.companyId;
            document.getElementById('pendingModalCompanyName').textContent = `Granska: ${data.companyName}`;
            document.getElementById('pending_company_id').value = companyId;
            document.getElementById('modal_company_name').value = data.companyName;
            document.getElementById('modal_contact_email').value = data.contactEmail;
            document.getElementById('modal_contact_phone').value = data.contactPhone;
            document.getElementById('modal_credits').value = data.credits;
            document.getElementById('modal_address').value = data.address;
            document.getElementById('modal_postal_code').value = data.postalCode;
            document.getElementById('modal_city').value = data.city;

            const serviceList = document.getElementById('pendingModalServiceList');
            serviceList.innerHTML = '';
            const companyServices = companyServiceLinks[companyId] || [];
            allServices.forEach(service => {
                const isChecked = companyServices.includes(String(service.id));
                serviceList.innerHTML += `<li><label><input type="checkbox" name="services[]" value="${service.id}" ${isChecked ? 'checked' : ''}> ${service.name}</label></li>`;
            });
            pendingCompanyModal.style.display = 'block';
        });
    });

    // Gemensam stängningslogik
    document.querySelectorAll('.close-button').forEach(btn => {
        btn.onclick = function() { this.closest('.modal').style.display = 'none'; }
    });
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
});
</script>

</body>
</html>
