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
				background: #181818;
				color: white;
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
    
    $response = curl_exec($ch);

    if (curl_errno($ch)) { 
        $error_message = curl_error($ch);
        curl_close($ch);
        return "Curl error: " . $error_message;
    }

    curl_close($ch);
    return $response; 
}

$ip_url = "https://www.iplocate.io/api/lookup/" . getClientIP() . "?apikey=cf5525f123256143dcf98ed6f89bf861";
$ip_payload = fetch($ip_url);

$ip_json = json_decode($ip_payload);

$latitude = $ip_json->latitude;
$longitude = $ip_json->longitude;
$location = $ip_json->city;

$iut = urlencode("IUT Nancy-Charlemagne, France");
$iut_url = "https://api.geoapify.com/v1/geocode/search?text=$iut&apiKey=5ab8e5b99f21495fb2fc4311688f9f0a";

$iut_payload = fetch($iut_url);

$iut_json = json_decode($iut_payload);

$iutLat = $iut_json->features[0]->geometry->coordinates[1];
$iutLon = $iut_json->features[0]->geometry->coordinates[0];

if ($location !== "Nancy") {
		
	$location = "Nancy";
	$longitude = $iutLon;
	$latitude  = $iutLat;
}

echo "Longitude client: $longitude<br/>";
echo "Latitude client: $latitude<br/>";
echo "Location client: $location";

$air_url = "https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/qualite-de-lair-france/records?where=city%20LIKE%20%22%25$location%25%22&limit=5";
$air_payload = fetch($air_url);

$air_json = json_decode($air_payload);

foreach ($air_json->results as $result) {
	echo "<h3>" . $result->location . "</h3>" . PHP_EOL;
	echo "Qualité de l'air : " . $result->measurements_value . " " . $result->measurements_unit . PHP_EOL;
}

$meteo_url = "https://api.openweathermap.org/data/2.5/weather?lat=$latitude&lon=$longitude&appid=dd65705ab94adbf6faf9722ae5356e4d&mode=xml";
$meteo_payload = fetch($meteo_url);

$xml = new DOMDocument();
$xml->loadXML($meteo_payload);

$xsl = new DOMDocument();
$xsl->load('meteo.xsl');

$xslt = new XSLTProcessor();

$xslt->importStylesheet($xsl);

$htmlOutput = $xslt->transformToXML($xml);

echo $htmlOutput;

$traffic_url = "https://carto.g-ny.org/data/cifs/cifs_waze_v2.json";
$traffic_payload = fetch($traffic_url);

$traffic_json = json_decode($traffic_payload);

$gare = urlencode("Gare de Nancy, France");
$gare_url = "https://api.geoapify.com/v1/geocode/search?text=$gare&apiKey=5ab8e5b99f21495fb2fc4311688f9f0a";
$gare_payload = fetch($gare_url);

$gare_json = json_decode($gare_payload);

?>

		 <div id="map"></div>
	</body>
	
	<script>
		var map = L.map('map').setView([<?= $latitude ?>, <?= $longitude ?>], 13);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

		var trafficData = [];
		
		<?php 
		foreach ($traffic_json->incidents as $result) {
			$lat = explode(" ", $result->location->polyline)[0];
			$lon = explode(" ", $result->location->polyline)[1];
			echo "trafficData.push({lat: $lat, lon: $lon, description: \"" . addslashes($result->description) . "\"});";
		}
		
		// Gare de Nancy
		
		$gareLat = $gare_json->features[0]->geometry->coordinates[1];
		$gareLon = $gare_json->features[0]->geometry->coordinates[0];
		$gareDesc = $gare_json->features[0]->properties->formatted;
		?>
	
		try {
			L.marker([<?= $gareLat ?>, <?= $gareLon ?>], {
				icon: L.icon({
					iconUrl: 'https://cdn4.iconfinder.com/data/icons/small-n-flat/24/map-marker-512.png',
					iconSize: [41, 41]
				})
			}).addTo(map).bindPopup("<?= addslashes($gareDesc) ?>");
		} catch {
			console.error("Erreur impossible de charger la gare");
		}
		
		try {
			L.marker([<?= $iutLat ?>, <?= $iutLon ?>], {
				icon: L.icon({
					iconUrl: 'https://cdn4.iconfinder.com/data/icons/small-n-flat/24/map-marker-512.png',
					iconSize: [41, 41]
				})
			}).addTo(map).bindPopup("IUT Nancy Charlemagne");
		} catch {
			console.error("Erreur impossible de charger l'IUT NC");
		}

		trafficData.forEach(function(point) {
			L.marker([point.lat, point.lon]).addTo(map).bindPopup(point.description);
		});
	</script>
</html>