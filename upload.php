<?php
$base_dir = $_SERVER['DOCUMENT_ROOT'];
$message = "";

function get_subdirs($dir) {
    $dirs = [];
    foreach (scandir($dir) as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($dir . '/' . $item)) {
            $dirs[] = $item;
        }
    }
    return $dirs;
}

$subdirs = get_subdirs($base_dir);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_dir = $base_dir . '/' . basename($_POST['target_dir']) . '/';
    $file = $_FILES['file'];
    $chunk = isset($_POST['chunk']) ? (int)$_POST['chunk'] : 0;
    $total_chunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 0;

    $filename = basename($file['name']);
    $file_path = $target_dir . $filename;

    if (!file_exists($target_dir)) {
        $message = "❌ Target folder doesn't exist.";
    } else {
        $temp_file_path = $file_path . '.part_' . $chunk;

        if (move_uploaded_file($file['tmp_name'], $temp_file_path)) {
            $message = "✅ Chunk $chunk uploaded.";

            if ($chunk == $total_chunks - 1) {
                $final_file = fopen($file_path, 'wb');
                for ($i = 0; $i < $total_chunks; $i++) {
                    $part_file = fopen($file_path . '.part_' . $i, 'rb');
                    while (!feof($part_file)) {
                        fwrite($final_file, fread($part_file, 8192));
                    }
                    fclose($part_file);
                    unlink($file_path . '.part_' . $i);
                }
                fclose($final_file);
                $message = "✅ File uploaded and merged successfully.";
            }
        } else {
            $message = "❌ Upload failed for chunk $chunk.";
        }
    }

    echo $message;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chunked File Uploader</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        #uploadStatus {
            font-weight: bold;
            color: green;
        }
    </style>
</head>
<body>
    <h2>Upload Large File to public_html (Chunked Upload)</h2>

    <form id="uploadForm" method="post" enctype="multipart/form-data">
        <input type="file" name="file" id="fileInput" required><br><br>

        <label>Choose upload folder:</label><br>
        <select name="target_dir" id="targetSelect" required>
            <?php foreach ($subdirs as $dir): ?>
                <option value="<?php echo htmlspecialchars($dir); ?>"><?php echo htmlspecialchars($dir); ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <input type="submit" value="Upload File">
    </form>

    <br>
    <div id="uploadStatus"></div>
    <div id="status"></div>

    <script>
        document.getElementById("uploadForm").onsubmit = function(event) {
            event.preventDefault();

            const fileInput = document.getElementById("fileInput");
            const file = fileInput.files[0];
            const chunkSize = 10 * 1024 * 1024; // 10MB
            const totalChunks = Math.ceil(file.size / chunkSize);
            let uploadedBytes = 0;
            let currentChunk = 0;

            function formatSize(bytes) {
                const mb = (bytes / 1024 / 1024).toFixed(2);
                return `${mb} MB`;
            }

            function uploadNextChunk() {
                if (currentChunk < totalChunks) {
                    const start = currentChunk * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    const chunk = file.slice(start, end);

                    const formData = new FormData();
                    formData.append("file", chunk, file.name);
                    formData.append("target_dir", document.getElementById("targetSelect").value);
                    formData.append("chunk", currentChunk);
                    formData.append("total_chunks", totalChunks);

                    const xhr = new XMLHttpRequest();
                    xhr.open("POST", "", true);

                    xhr.upload.addEventListener("progress", function (e) {
                        if (e.lengthComputable) {
                            const chunkPercent = ((e.loaded / chunk.size) * 100).toFixed(2);
                            const overallPercent = ((uploadedBytes + e.loaded) / file.size * 100).toFixed(2);

                            const chunkSizeDisplay = formatSize(chunk.size);
                            const uploadedDisplay = formatSize(uploadedBytes + e.loaded);
                            const totalSizeDisplay = formatSize(file.size);

                            document.getElementById("uploadStatus").innerText =
                                `Uploading chunk ${currentChunk + 1} of ${totalChunks}...\n` +
                                `Chunk progress: ${chunkPercent}% of ${chunkSizeDisplay}\n` +
                                `Overall progress: ${overallPercent}% (${uploadedDisplay} of ${totalSizeDisplay})`;
                        }
                    });

                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            uploadedBytes += chunk.size;
                            currentChunk++;
                            uploadNextChunk();
                        } else {
                            document.getElementById("uploadStatus").innerText = "❌ Upload failed.";
                        }
                    };

                    xhr.send(formData);
                } else {
                    document.getElementById("uploadStatus").innerText = "✅ File uploaded and merged successfully!";
                }
            }

            uploadNextChunk();
        };
    </script>
</body>
</html>
