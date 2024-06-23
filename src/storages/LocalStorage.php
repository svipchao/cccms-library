<?php
declare(strict_types=1);

namespace cccms\storages;

use think\facade\Filesystem;
use cccms\Storage;
use cccms\services\UserService;

class LocalStorage extends Storage
{
    /**
     * 上传文件
     * @param $files
     * @param int|string $folderOrCateId int 则为文件类型ID，string则为文件夹名称
     * @return array
     */
    public function upload($files, int|string $folderOrCateId = 0): array
    {
        if (empty($folderOrCateId)) return [];
        $res = $this->validateFile($files);
        $this->app->config->set([
            'disks' => [
                'local' => ['root' => $this->getLocalPath()]
            ]
        ], 'filesystem');
        $saveName = [];
        if (is_string($folderOrCateId)) {
            foreach ($res as $val) {
                $file_path = str_replace('\\', '/', Filesystem::putFile($folderOrCateId, $val, 'date("Y-m-d")'));
                $saveName[] = [
                    'file_path' => $file_path,
                    'file_url' => $this->request->domain() . '/uploads/' . $file_path,
                    'file_name' => $val->getoriginalName(),
                    'file_size' => $val->getSize(),
                    'file_ext' => $val->getOriginalExtension(),
                    'file_mime' => $val->getOriginalMime(),
                    'file_md5' => $val->md5(),
                    'file_sha1' => $val->sha1(),
                ];
            }
        } else {
            $user_id = UserService::instance()->getUserInfo('id');
            $path = $this->getCatePath($folderOrCateId);
            foreach ($res as $val) {
                $file_path = str_replace('\\', '/', Filesystem::putFile($path, $val, 'date("Y-m-d")'));
                $saveName[] = [
                    'user_id' => $user_id,
                    'cate_id' => $folderOrCateId,
                    'file_path' => $file_path,
                    'file_url' => $this->request->domain() . '/uploads/' . $file_path,
                    'file_name' => $val->getoriginalName(),
                    'file_size' => $val->getSize(),
                    'file_ext' => $val->getOriginalExtension(),
                    'file_mime' => $val->getOriginalMime(),
                    'file_md5' => $val->md5(),
                    'file_sha1' => $val->sha1(),
                    'file_code' => md5(mt_rand($user_id, time()) . $val->hashName() . $val->getPathname()),
                ];
            }
            $this->model->strict(false)->insertAll($saveName);
        }
        return count($saveName) > 1 ? $saveName : $saveName[0];
    }

    /**
     * 删除文件
     * @param int|string $folderOrCateId int 则为文件类型ID，string则为文件夹名称
     * @return bool
     */
    public function delete($folderOrCateId = 0): bool
    {
        if (is_string($folderOrCateId)) {
            // 替换..防止跨目录删除
            $filePath = $this->getLocalPath() . strtr($folderOrCateId, '..', '');
        } else {
            if (empty($folderOrCateId)) return false;
            $fileInfo = $this->model->with('cate')->findOrEmpty($folderOrCateId);
            if (!$fileInfo->isEmpty()) {
                $fileInfo->delete();
            }
            // 磁盘文件路径
            $filePath = $this->getLocalPath() . $fileInfo['file_path'];
        }
        // 判断附件是否在磁盘中
        if (file_exists($filePath) && !unlink($filePath)) {
            return false;
        }
        return true;
    }

    public function read()
    {
        // TODO: Implement list() method.
    }
}
