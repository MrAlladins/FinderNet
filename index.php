<?php
// Visa fel under utveckling
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inkludera databasanslutningen
require_once 'dbconn.php';

//======================================================================
// DEFINIERA ANNONSER
//======================================================================
// Lägg in dina reklambilder och länkar här.
// Du kan hämta denna data från en databas i framtiden.
$advertisements = [
    [
        'title' => 'Specialerbjudande från vår partner',
        'image_url' => 'https://placehold.co/600x400/4f46e5/ffffff?text=Din+Annons+Här', // Byt till din bild-URL
        'link_url' => 'https://dinsida.se/partner/erbjudande', // Byt till annonsens länk
        'alt_text' => 'Reklam för specialerbjudande'
    ],
    [
        'title' => 'Ett annat grymt erbjudande',
        'image_url' => 'https://placehold.co/600x400/16a34a/ffffff?text=Klicka+Här!', // Byt till din bild-URL
        'link_url' => 'https://dinsida.se/partner/kampanj', // Byt till annonsens länk
        'alt_text' => 'Reklam för kampanj'
    ]
];

//======================================================================
// HÄMTA DATA OCH LOGGA TRAFIK
//======================================================================
$services = [];
$partner_key = isset($_GET['partner']) ? trim($_GET['partner']) : null;

try {
    // Bygg SQL-frågan baserat på om det är en partnerlänk eller inte
    $sql = "SELECT s.id, s.name FROM services s";
    $params = [];

    if ($partner_key) {
        // Om det är en partner, hämta deras specifika tjänster
        $sql .= " JOIN partner_services ps ON s.id = ps.service_id JOIN partners p ON ps.partner_id = p.id WHERE p.partner_key = ?";
        $params[] = $partner_key;

        // LOGGA PARTNER-TRAFIK
        $stmt_partner_id = $pdo->prepare("SELECT id FROM partners WHERE partner_key = ?");
        $stmt_partner_id->execute([$partner_key]);
        $partner = $stmt_partner_id->fetch();
        if ($partner) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Okänd';
            
            $geo_data = @json_decode(file_get_contents("http://ip-api.com/json/{$ip_address}"));
            $city = $geo_data->city ?? null;
            $region = $geo_data->regionName ?? null;
            $country = $geo_data->country ?? null;
            $isp = $geo_data->isp ?? null;

            $stmt_log = $pdo->prepare(
                "INSERT INTO partner_traffic_log (partner_id, ip_address, user_agent, city, region, country, isp) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt_log->execute([$partner['id'], $ip_address, $user_agent, $city, $region, $country, $isp]);
        }
    } else {
        // Annars, hämta alla tjänster som är markerade som synliga
        $sql .= " WHERE s.is_visible = 1";
    }

    // Lägg till sorteringslogiken
    $sql .= " ORDER BY CASE WHEN s.name = 'Flygbiljetter' THEN 0 ELSE 1 END, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Kunde inte hämta tjänster: " . $e->getMessage());
}

// Array med ikoner
$service_icons = [
    'Målare' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.622 17.897-10.68-2.913"/><path d="M18.376 2.622a1 1 0 1 1 3.002 3.002L17.36 9.643a.5.5 0 0 0 0 .707l.944.944a2.41 2.41 0 0 1 0 3.408l-.944.944a.5.5 0 0 1-.707 0L8.354 7.348a.5.5 0 0 1 0-.707l.944-.944a2.41 2.41 0 0 1 3.408 0l.944.944a.5.5 0 0 0 .707 0z"/><path d="M9 8c-1.804 2.71-3.97 3.46-6.583 3.948a.507.507 0 0 0-.302.819l7.32 8.883a1 1 0 0 0 1.185.204C12.735 20.405 16 16.792 16 15"/></svg>',
    'Catering' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/><path d="M2 21h20"/><path d="M7 8v3"/><path d="M12 8v3"/><path d="M17 8v3"/><path d="M7 4h.01"/><path d="M12 4h.01"/><path d="M17 4h.01"/></svg>',
    'Flyttfirma' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>',
    'Elektriker' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
    'VVS' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/></svg>',
    'Snickare' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 12-9.373 9.373a1 1 0 0 1-3.001-3L12 9"/><path d="m18 15 4-4"/><path d="m21.5 11.5-1.914-1.914A2 2 0 0 1 19 8.172v-.344a2 2 0 0 0-.586-1.414l-1.657-1.657A6 6 0 0 0 12.516 3H9l1.243 1.243A6 6 0 0 1 12 8.485V10l2 2h1.172a2 2 0 0 1 1.414.586L18.5 14.5"/></svg>',
    'Städhjälp' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/></svg>',
    'Konsulter' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/></svg>',
    'Begagnade bilar' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 16.94V19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h1.34"/><path d="M18 16V8a2 2 0 0 0-2-2H8.34"/><path d="m15 16-3-3 3-3"/><circle cx="5" cy="19" r="2"/><circle cx="17" cy="19" r="2"/></svg>',
    'Motorcyklar' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v5l-4 4h16l-4-4V6z"/></svg>',
    'Fotografer' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>',
    'Båtar' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12l5.5 2.5L13 22l6.5-7.5L22 12l-5.5-2.5L11 2 4.5 9.5 2 12z"/><path d="M2 12h20"/></svg>',
    'Reparera bilen' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    'Trädgård & Utemiljö' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 22s10-4 10-10C12 6 2 2 2 2"/><path d="M22 22s-10-4-10-10C12 6 22 2 22 2"/></svg>',
    'Resor' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>',
    'Flygbiljetter' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 8-8 5 8 5 8-5-8-5z"/><path d="M2 12v6l8 5 8-5v-6"/><path d="m22 12-8-5-8 5"/></svg>',
    'Företagsevent' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="m13.2 6.3 8.5 8.5"/><path d="M5.8 11.3 8.3 3.6a1.2 1.2 0 0 1 2.1 0l2.5 7.7"/><path d="M12 22s-2.5-2-2.5-5 2.5-5 2.5-5"/><path d="M12 22s2.5-2 2.5-5-2.5-5-2.5-5"/><path d="m3.8 2.8 2.5 7.7"/></svg>',
    'After Work & Kick-off' => '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 0 0 10-10h-2a8 8 0 0 1-8 8 8 8 0 0 1-8-8h-2a10 10 0 0 0 10 10z"/><path d="M15 6.23a2.5 2.5 0 0 0-5 0"/><path d="M12 12v10"/></svg>'
];
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Välj Tjänst - Offertförfrågan</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f4f7f6; font-family: sans-serif; color: #333; }
        .container { max-width: 1200px; margin: 40px auto; padding: 1em; }
        .header-text { text-align: center; margin-bottom: 2.5em; }
        h1 { font-size: 2.5em; margin-bottom: 0.2em; }
        .header-text p { font-size: 1.2em; color: #666; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px; }
        @media (min-width: 1280px) { .card-grid { grid-template-columns: repeat(4, 1fr); } }
        
        /* -- CSS FÖR TJÄNSTEKORT (ÅTERSTÄLLD TILL ORIGINAL) -- */
        .service-card { 
            background-color: #fff; border: 1px solid #ddd; border-radius: 12px; 
            padding: 30px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            text-align: center; 
            text-decoration: none; 
            color: inherit; 
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .service-card .icon { width: 52px; height: 52px; margin: 0 auto 20px auto; color: #6366f1; }
        .service-card h3 { margin: 0; color: #111827; font-size: 1.3em; }

        .promo-banner { background-color: #eef2ff; border: 1px solid #c7d2fe; border-radius: 12px; padding: 2rem; margin-bottom: 2.5rem; text-align: center; }
        .promo-banner h2 { font-size: 1.5rem; font-weight: 700; color: #4338ca; margin: 0 0 0.5rem 0; }
        .promo-banner p { margin: 0 0 1rem 0; color: #4f46e5; }
        .promo-button { display: inline-block; background-color: #4f46e5; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background-color 0.2s ease; }
        .promo-button:hover { background-color: #4338ca; }

        /* -- CSS FÖR ANNONS-KORTET -- */
        .ad-card {
            position: relative;
            display: block;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            color: white;
            text-decoration: none;
            min-height: 200px; /* Sätter en minsta höjd för att det ska se bra ut i gridden */
        }
        .ad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .ad-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .ad-card .ad-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 60%);
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .ad-card h4 {
            margin: 0;
            font-size: 1.2em;
        }
        .ad-card .ad-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0,0,0,0.5);
            color: #fff;
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header-text">
        <h1>Vilken tjänst behöver du hjälp med?</h1>
        <p>Välj en kategori nedan för att specificera din förfrågan.</p>
    </div>

    <div class="promo-banner">
        <h2>Är du företagare?</h2>
        <p>Få tillgång till kvalificerade kundförfrågningar i ditt område. Anslut dig till vår plattform – helt gratis under lanseringsperioden.</p>
        <a href="presentation.php" class="promo-button">Läs mer & Anslut gratis</a>
    </div>

    <div class="card-grid">
        <?php
        $service_counter = 0;
        $ad_counter = 0;
        
        // Annonser visas efter var 4:e tjänst. Ändra denna siffra för att justera positionen.
        $ad_position = 4; 

        foreach ($services as $service):
            // Visa ett vanligt tjänstekort
        ?>
            <a href="questions.php?service_id=<?= htmlspecialchars($service['id']) ?>" class="service-card">
                <?= $service_icons[$service['name']] ?? '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>' ?>
                <h3><?= htmlspecialchars($service['name']) ?></h3>
            </a>
        <?php
            $service_counter++;

            // Kontrollera om det är dags att visa en annons
            if ($service_counter % $ad_position == 0 && $ad_counter < count($advertisements)) {
                $ad = $advertisements[$ad_counter];
        ?>
                <a href="<?= htmlspecialchars($ad['link_url']) ?>" target="_blank" rel="noopener sponsored" class="ad-card">
                    <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['alt_text']) ?>">
                    <div class="ad-overlay">
                        <h4><?= htmlspecialchars($ad['title']) ?></h4>
                    </div>
                    <span class="ad-badge">Annons</span>
                </a>
        <?php
                $ad_counter++;
            }
        endforeach; 
        ?>
    </div>
</div>
</body>
</html>
