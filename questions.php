<?php
// Visa fel under utveckling
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inkludera databasanslutningen och konfigurationsfil
require_once 'dbconn.php';
require_once 'config.php';

// 46elks SMS-funktion
function sendsms($sms) {
    $username = ELKS_USERNAME;
    $password = ELKS_PASSWORD;
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Authorization: Basic ' . base64_encode($username . ':' . $password) . "\r\n" .
                        "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($sms),
            'timeout' => 10
        )
    ));
    try {
        $response = file_get_contents("https://api.46elks.com/a1/sms", false, $context);
        if ($response === false || !strstr($http_response_header[0], "200 OK")) {
            return ['status' => 'failed', 'error' => $response ?: $http_response_header[0]];
        }
        return ['status' => 'sent', 'response' => $response];
    } catch (Exception $e) {
        return ['status' => 'failed', 'error' => $e->getMessage()];
    }
}

//======================================================================
// HANTERA INSKICKAD FÖRFRÅGAN (FORMULÄR-POST)
//======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
    $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    $customer_email = isset($_POST['customer_email']) ? trim($_POST['customer_email']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? trim($_POST['customer_phone']) : '';
    $selected_tasks = isset($_POST['tasks']) ? $_POST['tasks'] : [];
    $other_description = isset($_POST['other_task_description']) ? trim($_POST['other_task_description']) : '';
    $Youtubes = isset($_POST['question']) ? $_POST['question'] : [];

    if ($service_id && $postal_code && $customer_name && $customer_email) {
        try {
            $pdo->beginTransaction();

            // Spara förfrågan
            $sql_request = "INSERT INTO customer_requests (service_id, postal_code, customer_name, customer_email, customer_phone, other_task_description) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_request = $pdo->prepare($sql_request);
            $stmt_request->execute([$service_id, $postal_code, $customer_name, $customer_email, $customer_phone, $other_description]);
            $request_id = $pdo->lastInsertId();

            // Spara valda uppgifter
            if (!empty($selected_tasks)) {
                $sql_tasks = "INSERT INTO request_tasks (request_id, task_id) VALUES (?, ?)";
                foreach ($selected_tasks as $task_id) {
                    $stmt_tasks = $pdo->prepare($sql_tasks);
                    $stmt_tasks->execute([$request_id, $task_id]);
                }
            }

            // Spara svar från de anpassade frågorna
            if (!empty($Youtubes)) {
                $sql_answers = "INSERT INTO request_answers (request_id, question_id, answer_text) VALUES (?, ?, ?)";
                foreach ($Youtubes as $question_id => $answer_text) {
                    if (!empty(trim($answer_text))) {
                        $stmt_answers = $pdo->prepare($sql_answers);
                        $stmt_answers->execute([$request_id, $question_id, trim($answer_text)]);
                    }
                }
            }

            // Hämta tjänstens namn för tack-meddelandet
            $stmt_service = $pdo->prepare("SELECT name FROM services WHERE id = ?");
            $stmt_service->execute([$service_id]);
            $service = $stmt_service->fetch(PDO::FETCH_ASSOC);
            $service_name = $service ? $service['name'] : 'Okänd tjänst';

            // Hämta stad för tack-meddelandet
            $city = 'ditt område';
            $stmt_city = $pdo->prepare("SELECT city FROM postal_codes WHERE postal_code = ?");
            $stmt_city->execute([$postal_code]);
            $city_result = $stmt_city->fetch(PDO::FETCH_ASSOC);
            if ($city_result && $city_result['city']) {
                $city = $city_result['city'];
            }

            // Kontrollera SMS-status
            $stmt_sms_status = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'sms_enabled'");
            $stmt_sms_status->execute();
            $sms_status = $stmt_sms_status->fetch(PDO::FETCH_ASSOC);
            $sms_enabled = ($sms_status && $sms_status['setting_value'] === 'active');

            // Hämta kvalificerade företag (för lista)
            $postal_area_code = substr($postal_code, 0, 3); // T.ex. "922" från "92231"

            /*
            // ========================================================== //
            // ---- GAMMAL, STRIKT LOGIK (BORTKOMMENTERAD) ----
            // ========================================================== //
            $sql_companies = "
                SELECT DISTINCT c.id, c.company_name, c.contact_phone, c.credits
                FROM companies c
                JOIN company_services cs ON c.id = cs.company_id
                JOIN company_operating_areas coa ON c.id = coa.company_id
                JOIN company_subscriptions sub ON c.id = sub.company_id
                WHERE cs.service_id = ?
                AND coa.postal_area_code = ?
                AND c.sms_notifications_enabled = 1
                AND sub.service_name = 'sms_notifications'
                AND sub.status = 'active'
                AND c.credits >= 1
                AND c.status = 'active'
            ";
            $stmt_companies = $pdo->prepare($sql_companies);
            $stmt_companies->execute([$service_id, $postal_area_code]);
            $companies = $stmt_companies->fetchAll(PDO::FETCH_ASSOC);
            */

            // ========================================================== //
            // ---- NY, FÖRENKLAD LOGIK (AKTIV) ----
            // ========================================================== //
            $sql_companies = "
                SELECT DISTINCT c.id, c.company_name, c.contact_phone
                FROM companies c
                JOIN company_services cs ON c.id = cs.company_id
                JOIN company_operating_areas coa ON c.id = coa.company_id
                WHERE cs.service_id = ?
                AND coa.postal_area_code = ?
                AND c.status = 'active'
            ";
            $stmt_companies = $pdo->prepare($sql_companies);
            $stmt_companies->execute([$service_id, $postal_area_code]);
            $companies = $stmt_companies->fetchAll(PDO::FETCH_ASSOC);

            // Logga företagsresultat för felsökning
            if (!empty($companies)) {
                error_log("Hittade " . count($companies) . " företag för service_id {$service_id} och postal_area_code {$postal_area_code}: " . json_encode($companies, JSON_PRETTY_PRINT));
            } else {
                error_log("Inga kvalificerade företag hittades för service_id {$service_id} och postal_area_code {$postal_area_code}");

                // Felsök varje villkor i SQL-frågan
                $sql_check_services = "SELECT COUNT(*) as count FROM company_services WHERE service_id = ?";
                $stmt_check_services = $pdo->prepare($sql_check_services);
                $stmt_check_services->execute([$service_id]);
                $services_count = $stmt_check_services->fetch(PDO::FETCH_ASSOC)['count'];
                error_log("Felsökning: Hittade $services_count tjänster för service_id {$service_id}");

                $sql_check_areas = "SELECT COUNT(*) as count FROM company_operating_areas WHERE postal_area_code = ?";
                $stmt_check_areas = $pdo->prepare($sql_check_areas);
                $stmt_check_areas->execute([$postal_area_code]);
                $areas_count = $stmt_check_areas->fetch(PDO::FETCH_ASSOC)['count'];
                error_log("Felsökning: Hittade $areas_count områden för postal_area_code {$postal_area_code}");
                
                $sql_check_status = "SELECT COUNT(*) as count FROM companies WHERE status = 'active'";
                $stmt_check_status = $pdo->prepare($sql_check_status);
                $stmt_check_status->execute();
                $status_count = $stmt_check_status->fetch(PDO::FETCH_ASSOC)['count'];
                error_log("Felsökning: Hittade $status_count företag med status = 'active'");
            }

            // Förbered företagslista och resultatmeddelande för tack-sidan
            $notified_companies = [];
            $companies_result_message = !empty($companies) ? "Hittade " . count($companies) . " företag: " . implode(", ", array_column($companies, 'company_name')) : "Inga företag hittades för service_id {$service_id} och postnummer {$postal_code}.";
            if (!empty($companies)) {
                foreach ($companies as $company) {
                    $sms_sent = false;
                    $sms_error = $sms_enabled ? 'SMS ej skickat (okänd anledning)' : 'SMS-funktion pausad globalt';
                    if ($sms_enabled) {
                        // Skicka SMS till hårdkodat nummer
                        $message_body = "Ny förfrågan #{$request_id}: {$service_name}, {$customer_name}, {$postal_code}. Kontakt: {$customer_email}";
                        $sms = [
                            "from" => "FinderNet",
                            "to" => "+46702900213",
                            "message" => $message_body
                        ];
                        $sms_result = sendsms($sms);
                        $sms_status = $sms_result['status'];
                        $sms_error_detail = $sms_result['status'] === 'failed' ? $sms_result['error'] : null;

                        // Logga SMS-försöket
                        try {
                            $sql_sms_log = "INSERT INTO sms_log (company_id, request_id, phone_number, message_body, status, error_message) VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt_sms_log = $pdo->prepare($sql_sms_log);
                            $stmt_sms_log->execute([$company['id'], $request_id, "+46702900213", $message_body, $sms_status, is_string($sms_error_detail) ? $sms_error_detail : json_encode($sms_error_detail)]);
                            if ($sms_status === 'sent') {
                                $sms_sent = true;
                                $sms_error = null;
                            } else {
                                $sms_error = $sms_error_detail;
                            }
                        } catch (PDOException $e) {
                            error_log("Misslyckades att logga SMS i sms_log: " . $e->getMessage());
                            $sms_error = 'Fel vid loggning av SMS';
                        }
                    }
                    $notified_companies[] = [
                        'name' => $company['company_name'],
                        'sms_sent' => $sms_sent,
                        'sms_error' => $sms_error
                    ];
                }
            }

            $pdo->commit();
            // Tack-meddelande med ort
            $success_message = "Tack för din förfrågan! Företag som är aktiva i {$city} kommer kontakta dig inom kort.";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Ett tekniskt fel inträffade. Försök igen senare.";
            error_log("PDOException in questions.php: " . $e->getMessage());
        }
    } else {
        $error_message = "Vänligen fyll i alla obligatoriska fält.";
    }
}

//======================================================================
// HÄMTA DATA FÖR ATT VISA SIDAN (GET)
//======================================================================
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$service_name = 'Okänd tjänst'; // Fallback-värde för att undvika odefinierad variabel
if ($service_id === 0) { die("Ingen tjänst vald."); }
try {
    $stmt_service = $pdo->prepare("SELECT name FROM services WHERE id = ?");
    $stmt_service->execute([$service_id]);
    $service = $stmt_service->fetch(PDO::FETCH_ASSOC);
    if (!$service) { die("Tjänsten kunde inte hittas."); }
    $service_name = $service['name'];

    $stmt_tasks = $pdo->prepare("SELECT id, task_name FROM service_tasks WHERE service_id = ? ORDER BY task_name ASC");
    $stmt_tasks->execute([$service_id]);
    $all_tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

    $stmt_questions = $pdo->prepare("SELECT id, question_text, input_type, options FROM service_questions WHERE service_id = ? ORDER BY id ASC");
    $stmt_questions->execute([$service_id]);
    $custom_questions = $stmt_questions->fetchAll(PDO::FETCH_ASSOC);

    $grouped_tasks = [];
    if ($service_name === 'Konsulter' && !empty($all_tasks)) {
        $consultant_categories = [
            'Företagsledning & Strategi' => ['Managementkonsult', 'Verksamhetsutvecklare', 'Strategikonsult', 'Organisationskonsult', 'Processkonsult'],
            'IT & Teknik' => ['IT-konsult (allmänt)', 'Systemutvecklare / Programmerare', 'Cybersäkerhetskonsult', 'Nätverkstekniker', 'Molnarkitekt (t.ex. Azure, AWS)', 'Databasadministratör', 'AI/ML-konsult', 'AI expert', 'UX/UI-designer', 'Webdesigner'],
            'Ekonomi & Finans' => ['Ekonomikonsult', 'Redovisningskonsult', 'Controller (inhyrd)', 'Skatterådgivare', 'Finansiell rådgivare'],
            'Marknadsföring & Kommunikation' => ['Marknadskonsult', 'PR-konsult', 'Kommunikationsstrateg', 'SEO/SEM-specialist', 'Sociala medier-konsult', 'Copywriter'],
            'HR & Personal' => ['HR-konsult', 'Rekryteringskonsult', 'Arbetsmiljökonsult', 'Lönekonsult'],
            'Bygg & Fastighet' => ['Byggkonsult / Projektledare bygg', 'Kontrollansvarig (KA) enligt PBL', 'Energikonsult', 'VVS-konsult', 'Konstruktionskonsult'],
            'Juridik' => ['Juristkonsult']
        ];
        foreach ($all_tasks as $task) {
            foreach ($consultant_categories as $category => $category_tasks) {
                if (in_array($task['task_name'], $category_tasks)) {
                    $grouped_tasks[$category][] = $task;
                    break;
                }
            }
        }
    }
} catch (PDOException $e) {
    die("Kunde inte hämta data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Förfrågan för <?= htmlspecialchars($service_name) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f4f7f6; font-family: sans-serif; }
        .container { max-width: 800px; margin: 40px auto; padding: 2em 3em; border: 1px solid #ddd; border-radius: 8px; background-color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h1 { text-align: center; margin-bottom: 1.5em; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="text"], input[type="email"], input[type="tel"], input[type="number"], input[type="date"], textarea, select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .checklist { list-style-type: none; padding: 0; column-count: 2; }
        @media (min-width: 992px) { .checklist { column-count: 3; } }
        .checklist li { margin-bottom: 12px; }
        .checklist label { font-weight: normal; display: flex; align-items: center; }
        .checklist input { margin-right: 10px; }
        h3, h4 { margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px; color: #333; }
        h4 { margin-top: 25px; color: #0056b3; font-size: 1.1em; border: none; }
        .button { width: 100%; padding: 15px; font-size: 1.1em; font-weight: bold; }
        .postal-group { display: flex; align-items: center; gap: 15px; }
        .postal-group input { flex: 0 1 120px; }
        .postal-group #city-lookup-result { font-weight: bold; color: #333; flex: 1 1 auto; }
        .postal-group #check-icon { color: #28a745; width: 24px; height: 24px; display: none; }
        .success-message { color: #28a745; text-align: center; font-weight: bold; margin-bottom: 20px; }
        .company-list { list-style-type: none; padding: 0; margin-top: 20px; }
        .company-list li { padding: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; }
        .company-list .company-name { flex: 1; font-size: 1.1em; color: #333; }
        .company-list .sms-status { margin-left: 10px; }
        .company-list .sms-sent { color: #28a745; font-weight: bold; }
        .company-list .sms-failed { color: #dc3545; font-style: italic; }
        .company-list .sms-icon { margin-left: 5px; vertical-align: middle; }
        .companies-result { margin-bottom: 20px; color: #333; font-size: 1.1em; }
        .companies-result.success { color: #28a745; }
        .companies-result.empty { color: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h1>Förfrågan för: <?= htmlspecialchars($service_name) ?></h1>
    <?php if (isset($success_message)): ?>
        <p class="companies-result <?= !empty($notified_companies) ? 'success' : 'empty' ?>">
            <?= htmlspecialchars($companies_result_message) ?>
        </p>
        <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
        <?php if (!empty($notified_companies)): ?>
            <h3>Företag som mottagit din förfrågan</h3>
            <ul class="company-list">
                <?php foreach ($notified_companies as $company): ?>
                    <li>
                        <span class="company-name"><?= htmlspecialchars($company['name']) ?></span>
                        <span class="sms-status">
                            <?php if ($company['sms_sent']): ?>
                                <span class="sms-sent">SMS skickat
                                    <svg class="sms-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </span>
                            <?php else: ?>
                                <span class="sms-failed">Inget SMS (<?= htmlspecialchars($company['sms_error']) ?>)</span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Inga företag hittades i ditt område just nu, men din förfrågan har sparats.</p>
        <?php endif; ?>
    <?php elseif (isset($error_message)): ?>
        <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
    <?php else: ?>
    <form action="questions.php?service_id=<?= $service_id ?>" method="POST">
        <input type="hidden" name="service_id" value="<?= $service_id ?>">
        <h3>Dina uppgifter</h3>
        <div class="form-group">
            <label for="customer_name">Namn *</label>
            <input type="text" id="customer_name" name="customer_name" required>
        </div>
        <div class="form-group">
            <label for="postal_code">Postnummer *</label>
            <div class="postal-group">
                <input type="text" id="postal_code" name="postal_code" required maxlength="5" placeholder="123 45">
                <span id="city-lookup-result"></span>
                <svg id="check-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
        </div>
        <div class="form-group">
            <label for="customer_email">E-post *</label>
            <input type="email" id="customer_email" name="customer_email" required>
        </div>
        <div class="form-group">
            <label for="customer_phone">Telefon</label>
            <input type="tel" id="customer_phone" name="customer_phone">
        </div>
        <h3>Vad behöver du hjälp med?</h3>
        <?php if (in_array($service_name, ['Begagnade bilar', 'Motorcyklar', 'Fotografer', 'Båtar', 'Reparera bilen', 'Trädgård & Utemiljö', 'Resor', 'Flygbiljetter', 'Företagsevent', 'After Work & Kick-off'])): ?>
            <p>Vilken typ av uppdrag gäller det?</p>
            <ul class="checklist">
                <?php foreach ($all_tasks as $task): ?>
                    <li><label><input type="checkbox" name="tasks[]" value="<?= $task['id'] ?>"> <?= htmlspecialchars($task['task_name']) ?></label></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($custom_questions)): ?>
                <h3 style="margin-top: 20px;">Specifikationer</h3>
                <?php foreach ($custom_questions as $question): ?>
                    <div class="form-group">
                        <label for="question_<?= $question['id'] ?>"><?= htmlspecialchars($question['question_text']) ?></label>
                        <?php if ($question['input_type'] === 'textarea'): ?>
                            <textarea id="question_<?= $question['id'] ?>" name="question[<?= $question['id'] ?>]" rows="3"></textarea>
                        <?php elseif ($question['input_type'] === 'select'): ?>
                            <select id="question_<?= $question['id'] ?>" name="question[<?= $question['id'] ?>]">
                                <option value="">Välj...</option>
                                <?php foreach (explode(',', $question['options']) as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="<?= $question['input_type'] ?>" id="question_<?= $question['id'] ?>" name="question[<?= $question['id'] ?>]">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($service_name === 'Konsulter' && !empty($grouped_tasks)): ?>
            <p>Välj ett eller flera områden du behöver hjälp inom:</p>
            <?php foreach ($grouped_tasks as $category => $category_tasks): ?>
                <h4><?= htmlspecialchars($category) ?></h4>
                <ul class="checklist">
                    <?php foreach ($category_tasks as $task): ?>
                        <li><label><input type="checkbox" name="tasks[]" value="<?= $task['id'] ?>"> <?= htmlspecialchars($task['task_name']) ?></label></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php elseif (!empty($all_tasks)): ?>
            <p>Välj ett eller flera uppdrag:</p>
            <ul class="checklist">
                <?php foreach ($all_tasks as $task): ?>
                    <li><label><input type="checkbox" name="tasks[]" value="<?= $task['id'] ?>"> <?= htmlspecialchars($task['task_name']) ?></label></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="form-group">
            <label for="other_task_description">Annan beskrivning (om det du söker saknas)</label>
            <textarea id="other_task_description" name="other_task_description" rows="4"></textarea>
        </div>
        <button type="submit" class="button">Skicka förfrågan</button>
    </form>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const postalCodeInput = document.getElementById('postal_code');
    const cityResultDiv = document.getElementById('city-lookup-result');
    const checkIcon = document.getElementById('check-icon');
    let debounceTimer;
    postalCodeInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const postalCode = this.value.replace(/[^0-9]/g, ''); // Tillåt bara siffror
        this.value = postalCode; // Uppdatera fältet med rensat värde
        debounceTimer = setTimeout(() => {
            if (postalCode.length === 5) {
                fetch(`get_city.php?postal_code=${postalCode}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.city) {
                            cityResultDiv.textContent = data.city;
                            checkIcon.style.display = 'inline-block'; // Visa bocken
                        } else {
                            cityResultDiv.textContent = 'Okänd postort';
                            checkIcon.style.display = 'none'; // Dölj bocken
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching city:', error);
                        cityResultDiv.textContent = '';
                        checkIcon.style.display = 'none';
                    });
            } else {
                cityResultDiv.textContent = ''; // Rensa om postnumret inte är 5 siffror
                checkIcon.style.display = 'none'; // Dölj bocken
            }
        }, 300);
    });
});
</script>
</body>
</html>
