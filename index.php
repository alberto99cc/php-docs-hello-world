<?php

require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "trabajos";

if (!$connectionString) {
    die("La variable AZURE_STORAGE_CONNECTION_STRING no está configurada.");
}

$blobClient = BlobRestProxy::createBlobService($connectionString);

// Descargar archivo si se solicita
if (isset($_GET['download_blob'])) {
    $blobName = $_GET['download_blob'];
    try {
        $blob = $blobClient->getBlob($containerName, $blobName);
        $content = stream_get_contents($blob->getContentStream());

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($blobName) . '"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error al descargar el archivo: " . $e->getMessage();
        exit;
    }
}

// Eliminar archivo si se envió solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blob'])) {
    try {
        $blobClient->deleteBlob($containerName, $_POST['delete_blob']);
        echo "<p style='color:green;'>Archivo eliminado: {$_POST['delete_blob']}</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error al eliminar: {$e->getMessage()}</p>";
    }
}

// Subir archivo nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zipfile'])) {
    $file = $_FILES['zipfile'];
    if ($file['error'] === UPLOAD_ERR_OK && mime_content_type($file['tmp_name']) === 'application/zip') {
        $blobName = basename($file['name']);
        try {
            $content = fopen($file['tmp_name'], 'r');
            $blobClient->createBlockBlob($containerName, $blobName, $content);
            echo "<p style='color:green;'>Archivo subido: {$blobName}</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error al subir: {$e->getMessage()}</p>";
        }
    } else {
        echo "<p style='color:red;'>Solo se permiten archivos .zip válidos.</p>";
    }
}

// Listar blobs
try {
    $blobList = $blobClient->listBlobs($containerName, new ListBlobsOptions());
    $blobs = $blobList->getBlobs();
} catch (Exception $e) {
    die("Error al listar blobs: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor de archivos subidos en Azure Blob</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1 {
            color: #0078d4;
            border-bottom: 2px solid #0078d4;
            padding-bottom: 10px;
        }
        ul {
            list-style-type: none;
            padding-left: 0;
        }
        li {
            background: #ffffff;
            margin-bottom: 8px;
            padding: 10px 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        a {
            color: #0078d4;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
        form button {
            background-color: transparent;
            border: none;
            font-weight: bold;
            color: #d93025;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        form button:hover {
            background-color: #f8d7da;
        }
        h2 {
            margin-top: 40px;
            color: #004e8c;
        }
        form input[type="file"] {
            margin-bottom: 15px;
            font-size: 16px;
        }
        form button[type="submit"] {
            background-color: #0078d4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        form button[type="submit"]:hover {
            background-color: #005a9e;
        }
    </style>
</head>
<body>
    <h1>Mis archivos subidos en: '<?= htmlspecialchars($containerName) ?>'</h1>

    <ul>
    <?php if (empty($blobs)): ?>
        <li>No hay archivos.</li>
    <?php else: ?>
        <?php foreach ($blobs as $blob): ?>
            <li>
                <a href="?download_blob=<?= urlencode($blob->getName()) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar <?= htmlspecialchars($blob->getName()) ?>?')">
                    <input type="hidden" name="delete_blob" value="<?= htmlspecialchars($blob->getName()) ?>">
                    <button type="submit" aria-label="Eliminar <?= htmlspecialchars($blob->getName()) ?>">Eliminar</button>
                </form>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
    </ul>

    <h2>Subir nuevo archivo</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Subir</button>
    </form>
</body>
</html>
