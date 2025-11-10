<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'File Upload') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .upload-section {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .upload-section:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .upload-section.dragover {
            border-color: #667eea;
            background: #f0f2ff;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        input[type="file"] {
            display: none;
        }

        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 5px;
        }

        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .file-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            display: none;
        }

        .file-info.show {
            display: block;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .files-list {
            margin-top: 30px;
        }

        .file-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .file-meta {
            font-size: 14px;
            color: #666;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>📁 <?= e($title ?? 'File Upload') ?></h1>
            <p class="subtitle">Upload files to local storage or cloud (S3)</p>

            <div id="alert" class="alert"></div>

            <div class="upload-section" id="uploadZone">
                <div class="upload-icon">☁️</div>
                <h3>Drop files here or click to browse</h3>
                <p style="color: #999; margin-top: 10px;">Max file size: 10MB</p>
                <input type="file" id="fileInput" multiple>
            </div>

            <div id="fileInfo" class="file-info"></div>

            <div style="text-align: center;">
                <button class="btn" onclick="uploadToLocal()">📂 Upload to Local</button>
                <button class="btn btn-secondary" onclick="uploadToS3()">☁️ Upload to S3</button>
                <button class="btn btn-secondary" onclick="loadFiles()">🔄 Refresh Files</button>
            </div>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p style="margin-top: 10px; color: #666;">Uploading...</p>
            </div>
        </div>

        <div class="card">
            <h2>📋 Uploaded Files</h2>
            <div id="filesList" class="files-list">
                <p style="color: #999; text-align: center;">No files uploaded yet</p>
            </div>
        </div>
    </div>

    <script>
        let selectedFiles = [];
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const alert = document.getElementById('alert');
        const loading = document.getElementById('loading');

        // Click to browse
        uploadZone.addEventListener('click', () => fileInput.click());

        // File selection
        fileInput.addEventListener('change', (e) => {
            selectedFiles = Array.from(e.target.files);
            displaySelectedFiles();
        });

        // Drag and drop
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            selectedFiles = Array.from(e.dataTransfer.files);
            displaySelectedFiles();
        });

        function displaySelectedFiles() {
            if (selectedFiles.length === 0) {
                fileInfo.classList.remove('show');
                return;
            }

            const html = selectedFiles.map(file => `
                <div><strong>${file.name}</strong> - ${formatBytes(file.size)}</div>
            `).join('');

            fileInfo.innerHTML = html;
            fileInfo.classList.add('show');
        }

        async function uploadToLocal() {
            if (selectedFiles.length === 0) {
                showAlert('Please select files first', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', selectedFiles[0]); // Upload first file

            await uploadFile('/upload/local', formData);
        }

        async function uploadToS3() {
            if (selectedFiles.length === 0) {
                showAlert('Please select files first', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', selectedFiles[0]);

            await uploadFile('/upload/s3', formData);
        }

        async function uploadFile(url, formData) {
            try {
                loading.classList.add('show');
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    selectedFiles = [];
                    fileInput.value = '';
                    fileInfo.classList.remove('show');
                    loadFiles();
                } else {
                    showAlert(data.error || 'Upload failed', 'error');
                }
            } catch (error) {
                showAlert('Upload error: ' + error.message, 'error');
            } finally {
                loading.classList.remove('show');
            }
        }

        async function loadFiles() {
            try {
                const response = await fetch('/upload/list?disk=local');
                const data = await response.json();

                const filesList = document.getElementById('filesList');

                if (data.files.length === 0) {
                    filesList.innerHTML = '<p style="color: #999; text-align: center;">No files uploaded yet</p>';
                    return;
                }

                const html = data.files.map(file => `
                    <div class="file-item">
                        <div class="file-details">
                            <div class="file-name">${file.path.split('/').pop()}</div>
                            <div class="file-meta">
                                Size: ${formatBytes(file.size)} |
                                Type: ${file.mime_type} |
                                Modified: ${new Date(file.last_modified * 1000).toLocaleString()}
                            </div>
                        </div>
                        <div>
                            <a href="${file.url}" target="_blank" class="btn" style="padding: 8px 16px; font-size: 14px;">View</a>
                            <button class="btn btn-danger" style="padding: 8px 16px; font-size: 14px;"
                                    onclick="deleteFile('${file.path.split('/').pop()}')">Delete</button>
                        </div>
                    </div>
                `).join('');

                filesList.innerHTML = html;
            } catch (error) {
                console.error('Error loading files:', error);
            }
        }

        async function deleteFile(filename) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }

            try {
                const response = await fetch(`/upload/${filename}`, {
                    method: 'DELETE'
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    loadFiles();
                } else {
                    showAlert(data.error || 'Delete failed', 'error');
                }
            } catch (error) {
                showAlert('Delete error: ' + error.message, 'error');
            }
        }

        function showAlert(message, type) {
            alert.textContent = message;
            alert.className = `alert alert-${type} show`;

            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Load files on page load
        loadFiles();
    </script>
</body>
</html>
