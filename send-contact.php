<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

function smtpReadLine($socket): string
{
    $line = "";
    while (($chunk = fgets($socket, 515)) !== false) {
        $line .= $chunk;
        if (preg_match("/^\d{3}\s/", $chunk) === 1) {
            break;
        }
    }
    return $line;
}

function smtpExpect($socket, array $allowedCodes): bool
{
    $response = smtpReadLine($socket);
    $code = (int)substr($response, 0, 3);
    return in_array($code, $allowedCodes, true);
}

function smtpCommand($socket, string $command, array $allowedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $allowedCodes);
}

function smtpSendMail(
    string $host,
    int $port,
    string $username,
    string $password,
    string $fromEmail,
    string $fromName,
    string $to,
    string $subject,
    string $plainTextBody
): bool {
    $transport = $port === 465 ? "ssl://" . $host . ":" . $port : $host . ":" . $port;
    $socket = @stream_socket_client($transport, $errno, $errstr, 20);
    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 20);

    if (!smtpExpect($socket, [220])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "EHLO artiglow.shop", [250])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "AUTH LOGIN", [334])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, base64_encode($username), [334])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, base64_encode($password), [235])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "MAIL FROM:<" . $fromEmail . ">", [250])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "RCPT TO:<" . $to . ">", [250, 251])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "DATA", [354])) {
        fclose($socket);
        return false;
    }

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    $headers = [];
    $headers[] = "From: " . $fromName . " <" . $fromEmail . ">";
    $headers[] = "To: <" . $to . ">";
    $headers[] = "Reply-To: " . $fromEmail;
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "Subject: " . $encodedSubject;
    $headers[] = "Date: " . date(DATE_RFC2822);
    $data = implode("\r\n", $headers) . "\r\n\r\n" . $plainTextBody . "\r\n.";

    fwrite($socket, $data . "\r\n");
    $accepted = smtpExpect($socket, [250]);
    smtpCommand($socket, "QUIT", [221]);
    fclose($socket);
    return $accepted;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

$raw = file_get_contents("php://input");
$payload = json_decode($raw ?? "", true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Formato inválido."]);
    exit;
}

$name = trim((string)($payload["name"] ?? ""));
$phone = trim((string)($payload["phone"] ?? ""));
$eventType = trim((string)($payload["eventType"] ?? ""));
$details = trim((string)($payload["details"] ?? ""));
$to = trim((string)($payload["to"] ?? "info@artiglow.shop"));

if ($name === "" || $phone === "" || $eventType === "") {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Completa los campos obligatorios."]);
    exit;
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Correo de destino inválido."]);
    exit;
}

$safeName = str_replace(["\r", "\n"], " ", $name);
$safePhone = str_replace(["\r", "\n"], " ", $phone);
$safeEventType = str_replace(["\r", "\n"], " ", $eventType);
$safeDetails = str_replace(["\r", "\n"], " ", $details !== "" ? $details : "Sin detalle adicional");

$subject = "Nueva cotización desde web - " . $safeEventType;
$message = "Se recibió una solicitud desde el formulario de contacto.\n\n";
$message .= "Nombre: " . $safeName . "\n";
$message .= "Celular: " . $safePhone . "\n";
$message .= "Evento: " . $safeEventType . "\n";
$message .= "Detalle: " . $safeDetails . "\n";
$message .= "Fecha: " . date("Y-m-d H:i:s") . "\n";

$smtpHost = getenv("SMTP_HOST") ?: "smtp.hostinger.com";
$smtpPort = (int)(getenv("SMTP_PORT") ?: "465");
$smtpUser = getenv("SMTP_USER") ?: "info@artiglow.shop";
$smtpPass = getenv("SMTP_PASS") ?: "Arti@2026";
$smtpFrom = getenv("SMTP_FROM") ?: "info@artiglow.shop";
$smtpFromName = getenv("SMTP_FROM_NAME") ?: "ArtiGlow Web";

$sent = false;
if ($smtpUser !== "" && $smtpPass !== "") {
    $sent = smtpSendMail(
        $smtpHost,
        $smtpPort,
        $smtpUser,
        $smtpPass,
        $smtpFrom,
        $smtpFromName,
        $to,
        $subject,
        $message
    );
}

if (!$sent) {
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "From: ArtiGlow Web <info@artiglow.shop>";
    $headers[] = "Reply-To: info@artiglow.shop";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $sent = mail($to, $subject, $message, implode("\r\n", $headers));
}

if (!$sent) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "No se pudo enviar el correo."]);
    exit;
}

echo json_encode(["success" => true]);
