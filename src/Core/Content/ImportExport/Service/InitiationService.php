<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Exception\FileNotReadableException;
use Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Iterator\IteratorFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserEntity;

class InitiationService
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var EntityRepositoryInterface
     */
    private $fileRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $logRepository;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $userRepository;

    public function __construct(
        FilesystemInterface $filesystem,
        EntityRepositoryInterface $fileRepository,
        EntityRepositoryInterface $logRepository,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $userRepository
    ) {
        $this->filesystem = $filesystem;
        $this->fileRepository = $fileRepository;
        $this->logRepository = $logRepository;
        $this->iteratorFactory = $iteratorFactory;
        $this->userRepository = $userRepository;
    }

    /**
     * @throws UnexpectedFileTypeException
     */
    public function initiate(
        Context $context,
        string $activity,
        ImportExportProfileEntity $profileEntity,
        \DateTimeInterface $expireDate,
        ?string $filePath = null,
        ?string $originalFileName = null
    ): ImportExportLogEntity {
        if ($originalFileName === null) {
            $originalFileName = $this->generateFilename($profileEntity);
        }
        $fileEntity = $this->storeFile($context, $expireDate, $filePath, $originalFileName);
        $iterator = $this->iteratorFactory->create($context, $activity, $fileEntity, $profileEntity);
        $logEntity = $this->createLog($context, $activity, $fileEntity, $profileEntity, $iterator->count());

        $logEntity->setProfile($profileEntity);
        $logEntity->setFile($fileEntity);

        return $logEntity;
    }

    /**
     * @throws FileNotReadableException
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function storeFile(Context $context, \DateTimeInterface $expireDate, ?string $sourcePath, string $originalFileName): ImportExportFileEntity
    {
        $id = Uuid::randomHex();
        $path = ImportExportFileEntity::buildPath($id);
        if (!empty($sourcePath)) {
            if (!is_readable($sourcePath)) {
                throw new FileNotReadableException($sourcePath);
            }
            $sourceStream = fopen($sourcePath, 'rb');
            if (!is_resource($sourceStream)) {
                throw new FileNotReadableException($sourcePath);
            }
            $this->filesystem->putStream($path, $sourceStream);
        } else {
            $this->filesystem->put($path, '');
        }

        $fileData = [
            'id' => $id,
            'originalName' => $originalFileName,
            'path' => $path,
            'size' => $this->filesystem->getSize($path),
            'expireDate' => $expireDate,
            'accessToken' => ImportExportFileEntity::generateAccessToken(),
        ];

        $this->fileRepository->create([$fileData], $context);

        $fileEntity = new ImportExportFileEntity();
        $fileEntity->assign($fileData);

        return $fileEntity;
    }

    private function createLog(Context $context, string $activity, ImportExportFileEntity $file, ImportExportProfileEntity $profile, int $records): ImportExportLogEntity
    {
        $logEntity = new ImportExportLogEntity();
        $logEntity->setId(Uuid::randomHex());
        $logEntity->setActivity($activity);
        $logEntity->setState(ImportExportLogEntity::STATE_PROGRESS);
        $logEntity->setRecords($records);
        $logEntity->setProfileId($profile->getId());
        $logEntity->setProfileName($profile->getName());
        $logEntity->setFileId($file->getId());

        $contextSource = $context->getSource();
        $userId = $contextSource instanceof AdminApiSource ? $contextSource->getUserId() : null;
        if ($userId !== null) {
            $logEntity->setUsername($this->findUser($context, $userId)->getUsername());
            $logEntity->setUserId($userId);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($logEntity) {
            $logData = array_filter($logEntity->jsonSerialize(), function ($value) {
                return $value !== null;
            });
            $this->logRepository->create([$logData], $context);
        });

        return $logEntity;
    }

    private function generateFilename(ImportExportProfileEntity $profile): string
    {
        $extension = $profile->getFileType() === 'text/xml' ? 'xml' : 'csv';
        $timestamp = date('Ymd-His');

        return sprintf('%s_%s.%s', $profile->getName(), $timestamp, $extension);
    }

    private function findUser(Context $context, string $userId): UserEntity
    {
        return $this->userRepository->search(new Criteria([$userId]), $context)->first();
    }
}
