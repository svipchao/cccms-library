<?php
declare(strict_types=1);

namespace cccms\services;

use think\Image;
use cccms\{Service, Storage};

class UploadService extends Service
{
    /**
     * 文件上传
     * @param int|string $folderOrCateId int 则为文件类型ID，string则为文件夹名称
     * @return array
     */
    public function upload(int|string $folderOrCateId = 0): array
    {
        $file = static::$request->file('file');
        if (!empty($file)) {
            $file = Storage::instance()->upload($file, $folderOrCateId);
            if (in_array($file['file_ext'], ['jpg', 'gif', 'png', 'bmp', 'jpeg', 'wbmp'])) {
                // 图片压缩
                $compressLevel = ConfigService::getConfig('storage.compressLevel', 10);
                $compressLevel = max(1, min(10, $compressLevel));
                if ($compressLevel !== 10) {
                    $filePath = static::$app->getRootPath() . 'public/uploads/' . $file['file_path'];
                    $exif = exif_read_data($filePath);
                    $image = imagecreatefromjpeg($filePath);
                    if (isset($exif['Orientation'])) {
                        if ($exif['Orientation'] == 3) {
                            $result = imagerotate($image, 180, 0);
                            imagejpeg($result, $filePath, 100);
                        } elseif ($exif['Orientation'] == 6) {
                            $result = imagerotate($image, -90, 0);
                            imagejpeg($result, $filePath, 100);
                        } elseif ($exif['Orientation'] == 8) {
                            $result = imagerotate($image, 90, 0);
                            imagejpeg($result, $filePath, 100);
                        }
                    }
                    isset($result) && imagedestroy($result);
                    imagedestroy($image);
                    Image::open($filePath)->save($filePath, $file['file_ext'], $compressLevel * 10);
                }
            }
            return $file;
        }
        return [];
    }
}
