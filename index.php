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
    <meta charset="UTF-8" />
    <title>Entrega de trabajos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .container {
            background: white;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #0078d4;
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }
        input[type="file"] {
            margin-bottom: 20px;
        }
        button {
            background-color: #0078d4;
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #005a9e;
        }
        #status {
            margin-top: 20px;
            font-style: italic;
            color: #444;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Entrega de trabajos</h1>
        <?php
        $statusMsg = '';
        $statusClass = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['fileInput']) && $_FILES['fileInput']['error'] === 0) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = basename($_FILES['fileInput']['name']);
                $targetFilePath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['fileInput']['tmp_name'], $targetFilePath)) {
                    $statusMsg = "Archivo \"$fileName\" subido con éxito.";
                    $statusClass = 'success';
                } else {
                    $statusMsg = 'Error al subir el archivo.';
                    $statusClass = 'error';
                }
            } else {
                $statusMsg = 'Por favor, selecciona un archivo para subir.';
                $statusClass = 'error';
            }
        }
        ?>
        <form method="post" enctype="multipart/form-data">
            <label for="fileInput">Selecciona tu archivo:</label>
            <input type="file" id="fileInput" name="fileInput" />
            <button type="submit">Subir archivo</button>
        </form>
        <p id="status" class="<?php echo $statusClass; ?>"><?php echo $statusMsg; ?></p>
    </div>
</body>
</html>
