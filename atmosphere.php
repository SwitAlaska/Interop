<html>
	<head>
		<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
			integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
			crossorigin=""/>
			
		<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
			integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
			crossorigin=""></script>
		
		<style>
			@import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');
			
			body {
				font-family: "Roboto", serif;
				text-align: center;
				padding: 20px;
			}
		
			#map {
				height: 500px;
				width: 500px;
				display: block;
				margin: auto;
			}
		</style>
	</head>
	
	<body>
<?php

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function fetch($url) {
    $ch = curl_init($url);   

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	
	//curl_setopt($ch, CURLOPT_PROXY, 'www-cache'); // web etu
    //curl_setopt($ch, CURLOPT_PROXYPORT, 3128);  // web etu
    
    $response = curl_exec($ch);

    if (curl_errno($ch)) { 
        $error_message = curl_error($ch);
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return $response; 
}

$latitude = null;
$longitude = null;
$location = "Inconnu";

$ip_url = "https://www.iplocate.io/api/lookup/" . getClientIP() . "?apikey=cf5525f123256143dcf98ed6f89bf861";
$ip_payload = fetch($ip_url);

if ($ip_payload) {
    $ip_json = json_decode($ip_payload);
    if ($ip_json && isset($ip_json->latitude, $ip_json->longitude, $ip_json->city)) {
        $latitude = $ip_json->latitude;
        $longitude = $ip_json->longitude;
        $location = $ip_json->city;
    }
}

$iut = urlencode("IUT Nancy-Charlemagne, France");
$iut_url = "https://api.geoapify.com/v1/geocode/search?text=$iut&apiKey=5ab8e5b99f21495fb2fc4311688f9f0a";
$iut_payload = fetch($iut_url);

$iutLat = null;
$iutLon = null;
if ($iut_payload) {
    $iut_json = json_decode($iut_payload);
    if ($iut_json && isset($iut_json->features[0]->geometry->coordinates)) {
        $iutLat = $iut_json->features[0]->geometry->coordinates[1];
        $iutLon = $iut_json->features[0]->geometry->coordinates[0];
    }
}

if (!$latitude || !$longitude || $location !== "Nancy") {
    $location = "Nancy";
    $longitude = $iutLon;
    $latitude  = $iutLat;
}

echo "Longitude client: " . ($longitude ?? 'Indisponible') . "<br/>";
echo "Latitude client: " . ($latitude ?? 'Indisponible') . "<br/>";
echo "Location client: $location";

$air_url = "https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/qualite-de-lair-france/records?where=city%20LIKE%20%22%25$location%25%22&limit=5";
$air_payload = fetch($air_url);

if ($air_payload) {
    $air_json = json_decode($air_payload);
    if ($air_json && isset($air_json->results)) {
        foreach ($air_json->results as $result) {
            echo "<h3>" . ($result->location ?? 'Inconnu') . "</h3>" . PHP_EOL;
            echo "Qualité de l'air : " . ($result->measurements_value ?? 'N/A') . " " . ($result->measurements_unit ?? '') . PHP_EOL;
        }
    } else {
        echo "<p>Pas de données disponibles sur la qualité de l'air.</p>";
    }
}

$meteo_url = "https://api.openweathermap.org/data/2.5/weather?lat=$latitude&lon=$longitude&appid=dd65705ab94adbf6faf9722ae5356e4d&mode=xml";
$meteo_payload = fetch($meteo_url);

if ($meteo_payload) {
    $xml = new DOMDocument();
    if (@$xml->loadXML($meteo_payload)) {
        $xsl = new DOMDocument();
        if (@$xsl->load('meteo.xsl')) {
            $xslt = new XSLTProcessor();
            $xslt->importStylesheet($xsl);
            $htmlOutput = @$xslt->transformToXML($xml);
            echo $htmlOutput;
        } else {
            echo "<p>Impossible de charger la feuille de style XSL.</p>";
        }
    } else {
        echo "<p>Impossible de traiter les données météo.</p>";
    }
} else {
    echo "<p>Pas de données météo disponibles.</p>";
}

$traffic_url = "https://carto.g-ny.org/data/cifs/cifs_waze_v2.json";
$traffic_payload = fetch($traffic_url);

$traffic_data = [];
if ($traffic_payload) {
    $traffic_json = json_decode($traffic_payload);
    if ($traffic_json && isset($traffic_json->incidents)) {
        $traffic_data = $traffic_json->incidents;
    }
}

$gare = urlencode("Gare de Nancy, France");
$gare_url = "https://api.geoapify.com/v1/geocode/search?text=$gare&apiKey=5ab8e5b99f21495fb2fc4311688f9f0a";
$gare_payload = fetch($gare_url);

$gareLat = null;
$gareLon = null;
$gareDesc = null;
if ($gare_payload) {
    $gare_json = json_decode($gare_payload);
    if ($gare_json && isset($gare_json->features[0]->geometry->coordinates, $gare_json->features[0]->properties->formatted)) {
        $gareLat = $gare_json->features[0]->geometry->coordinates[1];
        $gareLon = $gare_json->features[0]->geometry->coordinates[0];
        $gareDesc = $gare_json->features[0]->properties->formatted;
    }
}

?>

		 <div id="map"></div>
		 <a href="https://github.com/SwitAlaska/Interop">Lien GitHub</a>
	</body>
	
	<script>
		var map = L.map('map').setView([<?= $latitude ?? 48.6921 ?>, <?= $longitude ?? 6.1844 ?>], 13);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

		var trafficData = [];
		<?php 
		if (!empty($traffic_data)) {
			foreach ($traffic_data as $result) {
				if (isset($result->location->polyline)) {
					$lat = explode(" ", $result->location->polyline)[0] ?? null;
					$lon = explode(" ", $result->location->polyline)[1] ?? null;
					if ($lat && $lon) {
						echo "trafficData.push({lat: $lat, lon: $lon, description: \"" . addslashes($result->description ?? 'Description indisponible') . "\"});";
					}
				}
			}
		}
		?>

		try {
			if (<?= $gareLat && $gareLon ? 'true' : 'false' ?>) {
				L.marker([<?= $gareLat ?>, <?= $gareLon ?>], {
					icon: L.icon({
						iconUrl: 'https://cdn4.iconfinder.com/data/icons/small-n-flat/24/map-marker-512.png',
						iconSize: [41, 41]
					})
				}).addTo(map).bindPopup("<?= addslashes($gareDesc ?? 'Gare de Nancy') ?>");
			}
		} catch {
			console.error("Erreur impossible de charger la gare");
		}
		
		try {
			if (<?= $iutLat && $iutLon ? 'true' : 'false' ?>) {
				L.marker([<?= $iutLat ?>, <?= $iutLon ?>], {
					icon: L.icon({
						iconUrl: 'https://cdn4.iconfinder.com/data/icons/small-n-flat/24/map-marker-512.png',
						iconSize: [41, 41]
					})
				}).addTo(map).bindPopup("IUT Nancy Charlemagne");
			}
		} catch {
			console.error("Erreur impossible de charger l'IUT NC");
		}

		trafficData.forEach(function(point) {
			L.marker([point.lat, point.lon]).addTo(map).bindPopup(point.description);
		});
	</script>
</html>
