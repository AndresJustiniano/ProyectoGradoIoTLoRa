<?php

// ===============================================
// CONFIGURACIÓN PRINCIPAL
// ===============================================

// Device ID de ThingsBoard
$deviceId = "d30a4dd0-ceac-11f0-b238-bd8a9470eef2";

// JWT token de My Profile → Security → Generate Token
$jwtToken = "eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJhbmRyZXNqdXN0aW5pYW5vMDI5OEBnbWFpbC5jb20iLCJ1c2VySWQiOiJlNzM2ODlhMC1jZTk3LTExZjAtYjIzOC1iZDhhOTQ3MGVlZjIiLCJzY29wZXMiOlsiVEVOQU5UX0FETUlOIl0sInNlc3Npb25JZCI6ImM2YTFiZjUwLTJlZGItNGZjOS05ZTk4LWI0NTNhMDkwZjhjMyIsImV4cCI6MTc2NDY0OTg4OCwiaXNzIjoidGhpbmdzYm9hcmQuY2xvdWQiLCJpYXQiOjE3NjQ2MjEwODgsImZpcnN0TmFtZSI6IkFuZHJlcyBBcnR1cm8iLCJsYXN0TmFtZSI6Ikp1c3Rpbmlhbm8gR2FyZWNhIiwiZW5hYmxlZCI6dHJ1ZSwiaXNQdWJsaWMiOmZhbHNlLCJpc0JpbGxpbmdTZXJ2aWNlIjpmYWxzZSwicHJpdmFjeVBvbGljeUFjY2VwdGVkIjp0cnVlLCJ0ZXJtc09mVXNlQWNjZXB0ZWQiOnRydWUsInRlbmFudElkIjoiZTcwMWJlMDAtY2U5Ny0xMWYwLWIyMzgtYmQ4YTk0NzBlZWYyIiwiY3VzdG9tZXJJZCI6IjEzODE0MDAwLTFkZDItMTFiMi04MDgwLTgwODA4MDgwODA4MCJ9.DJ1cCa5riNlesA7XrfCjCdiWscoT65FsDQLLqRJkqDcUo4zj4X-Ed-7syXomtGDM49rU42SVqjbp8yPC2luWNw";

// Campos de telemetría a solicitar
$keys = "voltage,current,power,consumption,fwd,rfl,swr,temperature,humidity,relay";

// URL REST de ThingsBoard
$url = "https://thingsboard.cloud/api/plugins/telemetry/DEVICE/$deviceId/values/timeseries?keys=$keys";


// ===============================================
// CONEXIÓN A LA BASE DE DATOS (una sola vez)
// ===============================================
$conexion = new mysqli("localhost", "root", "", "telsat_db");

if ($conexion->connect_error) {
    die("❌ Error de conexión a MariaDB: " . $conexion->connect_error);
}

echo "✔ Conectado a MariaDB\n";


// ===============================================
// FUNCIÓN PARA EXTRAER EL ÚLTIMO VALOR REAL
// ===============================================
function takeLast($arr, $key) {
    if (!isset($arr[$key]) || !is_array($arr[$key]) || count($arr[$key]) === 0) {
        return null;
    }
    return floatval(end($arr[$key])["value"]);
}


// ===============================================
// BUCLE INFINITO — CONSULTAR CADA 5 SEGUNDOS
// ===============================================
echo "⏳ Servicio iniciado. Insertando telemetría cada 5 segundos...\n";

while (true) {

    // -------------------------------
    // Solicitud HTTP a ThingsBoard
    // -------------------------------
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Authorization: Bearer $jwtToken"
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        echo "❌ Error cURL: " . curl_error($ch) . "\n";
        curl_close($ch);
        sleep(5);
        continue;
    }

    curl_close($ch);


    // -------------------------------
    // Decodificar JSON
    // -------------------------------
    $data = json_decode($response, true);

    if (!is_array($data)) {
        echo "❌ Error: JSON inválido\n";
        sleep(5);
        continue;
    }


    // -------------------------------
    // Extraer valores
    // -------------------------------
    $voltage     = takeLast($data, "voltage");
    $current     = takeLast($data, "current");
    $power       = takeLast($data, "power");
    $consumption = takeLast($data, "consumption");
    $fwd         = takeLast($data, "fwd");
    $rfl         = takeLast($data, "rfl");
    $swr         = takeLast($data, "swr");
    $temperature = takeLast($data, "temperature");
    $humidity    = takeLast($data, "humidity");
    $relay       = takeLast($data, "relay");


    // -------------------------------
    // Insertar en MariaDB
    // -------------------------------
    $stmt = $conexion->prepare("
        INSERT INTO telemetry (
            voltage, current, power, consumption, fwd, rfl, swr,
            temperature, humidity, relay
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo "❌ Error SQL prepare(): " . $conexion->error . "\n";
        sleep(5);
        continue;
    }

    $stmt->bind_param(
        "dddddddddi",
        $voltage,
        $current,
        $power,
        $consumption,
        $fwd,
        $rfl,
        $swr,
        $temperature,
        $humidity,
        $relay
    );

    if ($stmt->execute()) {
        echo "✔ Registro insertado: " . date("H:i:s") . "\n";
    } else {
        echo "❌ Error al insertar: " . $stmt->error . "\n";
    }

    $stmt->close();

    // -------------------------------
    // Esperar 5 segundos
    // -------------------------------
    sleep(5);
}

$conexion->close();
?>

