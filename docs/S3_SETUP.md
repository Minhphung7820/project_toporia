# S3 Storage Setup Guide

Quick guide to configure S3-compatible storage for Toporia Framework.

## Prerequisites

AWS SDK PHP is already installed:
```bash
composer require aws/aws-sdk-php
```

Current version: **3.359.8** ✅

---

## AWS S3 Setup

### 1. Create S3 Bucket

1. Go to [AWS S3 Console](https://console.aws.amazon.com/s3/)
2. Click "Create bucket"
3. Enter bucket name (e.g., `my-app-storage`)
4. Choose region (e.g., `us-east-1`)
5. Configure permissions (Public/Private)
6. Create bucket

### 2. Create IAM User

1. Go to [IAM Console](https://console.aws.amazon.com/iam/)
2. Create new user with programmatic access
3. Attach policy: `AmazonS3FullAccess`
4. Save **Access Key ID** and **Secret Access Key**

### 3. Configure Environment

Add to `.env`:
```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your-access-key-id
AWS_SECRET_ACCESS_KEY=your-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=my-app-storage
```

### 4. Test Connection

```php
use Toporia\Framework\Support\Accessors\Storage;

// Store file to S3
Storage::disk('s3')->put('test.txt', 'Hello S3!');

// Get file from S3
$content = Storage::disk('s3')->get('test.txt');

// Generate public URL
$url = Storage::disk('s3')->url('test.txt');
// https://my-app-storage.s3.us-east-1.amazonaws.com/test.txt

// Generate temporary URL (1 hour)
$tempUrl = Storage::disk('s3')->temporaryUrl('test.txt', 3600);
```

---

## DigitalOcean Spaces Setup

DigitalOcean Spaces is S3-compatible storage.

### 1. Create Space

1. Go to [DigitalOcean Spaces](https://cloud.digitalocean.com/spaces)
2. Click "Create Space"
3. Choose datacenter region (e.g., `nyc3`)
4. Enter space name
5. Set CDN option
6. Create space

### 2. Generate API Keys

1. Go to API → Spaces Access Keys
2. Generate new key
3. Save **Access Key** and **Secret Key**

### 3. Configure Environment

Add to `.env`:
```env
FILESYSTEM_DISK=spaces

DO_SPACES_KEY=your-spaces-key
DO_SPACES_SECRET=your-spaces-secret
DO_SPACES_REGION=nyc3
DO_SPACES_BUCKET=my-space-name
DO_SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
DO_SPACES_URL=https://my-space-name.nyc3.cdn.digitaloceanspaces.com
```

### 4. Test Connection

```php
use Toporia\Framework\Support\Accessors\Storage;

// Upload to Spaces
Storage::disk('spaces')->put('uploads/photo.jpg', $imageData);

// Get public URL (via CDN)
$url = Storage::disk('spaces')->url('uploads/photo.jpg');
// https://my-space-name.nyc3.cdn.digitaloceanspaces.com/uploads/photo.jpg
```

---

## MinIO Setup (Self-hosted S3)

MinIO is open-source S3-compatible storage.

### 1. Run MinIO with Docker

```bash
docker run -d \
  --name minio \
  -p 9000:9000 \
  -p 9001:9001 \
  -e MINIO_ROOT_USER=minioadmin \
  -e MINIO_ROOT_PASSWORD=minioadmin \
  -v /data/minio:/data \
  minio/minio server /data --console-address ":9001"
```

### 2. Create Bucket

1. Open MinIO Console: http://localhost:9001
2. Login with `minioadmin` / `minioadmin`
3. Create new bucket: `my-bucket`
4. Set access policy (public/private)

### 3. Configure Environment

Add to `.env`:
```env
FILESYSTEM_DISK=minio

MINIO_KEY=minioadmin
MINIO_SECRET=minioadmin
MINIO_REGION=us-east-1
MINIO_BUCKET=my-bucket
MINIO_ENDPOINT=http://localhost:9000
MINIO_URL=http://localhost:9000
```

### 4. Test Connection

```php
use Toporia\Framework\Support\Accessors\Storage;

// Upload to MinIO
Storage::disk('minio')->put('files/document.pdf', $pdfData);

// Get file
$content = Storage::disk('minio')->get('files/document.pdf');

// Public URL
$url = Storage::disk('minio')->url('files/document.pdf');
// http://localhost:9000/my-bucket/files/document.pdf
```

---

## Usage Examples

### File Upload

```php
use Toporia\Framework\Storage\UploadedFile;
use Toporia\Framework\Support\Accessors\Storage;

// Handle upload
$file = UploadedFile::createFromArray($_FILES['upload']);

// Store to S3 (public)
$path = $file->storePublicly('uploads', null, 's3');

// Get public URL
$url = Storage::disk('s3')->url($path);

// Return to client
return response()->json([
    'path' => $path,
    'url' => $url
]);
```

### Direct Upload

```php
use Toporia\Framework\Support\Accessors\Storage;

// Upload image
$imageData = file_get_contents('/path/to/image.jpg');
Storage::disk('s3')->put('images/photo.jpg', $imageData, [
    'visibility' => 'public'
]);

// Upload with custom content type
Storage::disk('s3')->put('videos/movie.mp4', $videoStream, [
    'visibility' => 'public',
    'ContentType' => 'video/mp4'
]);
```

### File Operations

```php
use Toporia\Framework\Support\Accessors\Storage;

$disk = Storage::disk('s3');

// Check exists
if ($disk->exists('file.txt')) {
    // File exists
}

// Get file info
$size = $disk->size('file.txt');
$lastModified = $disk->lastModified('file.txt');
$mimeType = $disk->mimeType('file.txt');

// Copy file
$disk->copy('old.txt', 'new.txt');

// Move file
$disk->move('temp.txt', 'permanent.txt');

// Delete file
$disk->delete('unwanted.txt');

// Delete multiple
$disk->delete(['file1.txt', 'file2.txt', 'file3.txt']);
```

### Directory Operations

```php
use Toporia\Framework\Support\Accessors\Storage;

$disk = Storage::disk('s3');

// List files in directory
$files = $disk->files('uploads');

// List files recursively
$allFiles = $disk->files('uploads', true);

// List subdirectories
$dirs = $disk->directories('uploads');

// Create directory (in S3, created implicitly)
$disk->makeDirectory('new-folder');

// Delete directory and contents
$disk->deleteDirectory('old-folder');
```

### Temporary URLs

```php
use Toporia\Framework\Support\Accessors\Storage;

// Generate signed URL (valid for 1 hour)
$url = Storage::disk('s3')->temporaryUrl('private/document.pdf', 3600);

// Use in email/response
$downloadLink = $url;
// https://bucket.s3.region.amazonaws.com/private/document.pdf?X-Amz-Signature=...
```

---

## Performance Tips

### 1. Use Streaming for Large Files

```php
// ✅ Good: Stream large file
$stream = fopen('/path/to/large-video.mp4', 'r');
Storage::disk('s3')->put('videos/movie.mp4', $stream);
fclose($stream);

// ❌ Bad: Load entire file into memory
$data = file_get_contents('/path/to/large-video.mp4');
Storage::disk('s3')->put('videos/movie.mp4', $data);
```

### 2. Use Batch Operations

```php
// ✅ Good: Delete multiple files at once
Storage::disk('s3')->delete(['file1.txt', 'file2.txt', 'file3.txt']);

// ❌ Bad: Delete one by one
foreach ($files as $file) {
    Storage::disk('s3')->delete($file);
}
```

### 3. Cache Disk Instances

```php
// ✅ Good: Reuse disk instance
$s3 = Storage::disk('s3');
$s3->put('file1.txt', 'data');
$s3->put('file2.txt', 'data');
$s3->put('file3.txt', 'data');

// ❌ Bad: Resolve disk multiple times
Storage::disk('s3')->put('file1.txt', 'data');
Storage::disk('s3')->put('file2.txt', 'data');
Storage::disk('s3')->put('file3.txt', 'data');
```

---

## Troubleshooting

### Error: "AWS SDK not installed"

```bash
# Install AWS SDK
composer require aws/aws-sdk-php

# Verify installation
php -r "echo class_exists('\Aws\S3\S3Client') ? 'OK' : 'Not found';"
```

### Error: "The AWS Access Key Id you provided does not exist"

- Check `AWS_ACCESS_KEY_ID` in `.env`
- Verify IAM user has correct permissions
- Ensure access key is active (not deleted/disabled)

### Error: "The specified bucket does not exist"

- Check `AWS_BUCKET` name in `.env`
- Ensure bucket exists in correct region
- Verify bucket name is correct (case-sensitive)

### Error: "SignatureDoesNotMatch"

- Check `AWS_SECRET_ACCESS_KEY` is correct
- Ensure no extra spaces in `.env` values
- Verify system time is synchronized (important for signing)

### Slow Upload Speed

```php
// Enable multipart upload for large files (handled automatically)
// AWS SDK uses multipart for files > 5MB by default

// Adjust timeout if needed
$s3Client->putObject([
    'Bucket' => 'my-bucket',
    'Key' => 'large-file.zip',
    'Body' => $stream,
    '@http' => [
        'timeout' => 300, // 5 minutes
        'connect_timeout' => 30
    ]
]);
```

---

## Security Best Practices

### 1. Never Commit Credentials

```bash
# ✅ Good: Use .env (gitignored)
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=yyy

# ❌ Bad: Hardcode in config
'key' => 'AKIAIOSFODNN7EXAMPLE',
```

### 2. Use IAM Roles (AWS EC2/ECS)

```php
// On AWS EC2/ECS, use IAM roles instead of keys
// SDK automatically uses instance credentials
$s3 = new S3Filesystem(
    bucket: 'my-bucket',
    region: 'us-east-1',
    key: '',  // Empty - uses IAM role
    secret: ''
);
```

### 3. Restrict Bucket Permissions

```json
// S3 Bucket Policy - Allow only specific IPs
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Deny",
    "Principal": "*",
    "Action": "s3:*",
    "Resource": "arn:aws:s3:::my-bucket/*",
    "Condition": {
      "NotIpAddress": {
        "aws:SourceIp": ["1.2.3.4/32"]
      }
    }
  }]
}
```

### 4. Use Temporary URLs for Private Files

```php
// Don't expose permanent URLs for private files
// Use temporary signed URLs instead
$url = Storage::disk('s3')->temporaryUrl('private/file.pdf', 3600);
```

---

## Cost Optimization

### 1. Use Lifecycle Policies

Configure S3 lifecycle to automatically:
- Move old files to Glacier (cheaper)
- Delete temporary files after X days

### 2. Enable Compression

```php
// Compress before upload
$data = gzencode($content);
Storage::disk('s3')->put('file.txt.gz', $data, [
    'ContentEncoding' => 'gzip'
]);
```

### 3. Use CloudFront CDN

- Set `AWS_URL` to CloudFront domain
- Reduce S3 request costs
- Improve download speed

---

## Related Documentation

- [Storage System](STORAGE.md)
- [File Upload Guide](FILE_UPLOAD.md)
- [AWS S3 Documentation](https://docs.aws.amazon.com/s3/)
- [DigitalOcean Spaces](https://docs.digitalocean.com/products/spaces/)
- [MinIO Documentation](https://min.io/docs/)
