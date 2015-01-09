<?php

namespace Controller;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Twig_Environment as Twig;

class AmazonS3Controller
{
    /**
     * @var Twig
     */
    private $twig;

    /**
     * @var S3Client
     */
    private $s3Client;

    public function __construct(Twig $twig, S3Client $s3Client)
    {
        $this->twig = $twig;
        $this->s3Client = $s3Client;
    }

    /**
     * @param  string $bucket
     * @return string
     */
    public function listAction($bucket)
    {
        $errors = [];

        $buckets = [];
        try {
            $result = $this->s3Client->listBuckets();
            $buckets = $result->get('Buckets');
        } catch (S3Exception $e) {
            $errors[] = sprintf('Cannot retrieve buckets: %s', $e->getMessage());
        }

        $objects = [];
        if (!empty($bucket)) {
            try {
                $maxIteration = 10;
                $iteration = 0;
                $marker = '';
                do {
                    $result = $this->s3Client->listObjects(['Bucket' => $bucket, 'Marker' => $marker]);
                    if ($result->get('Contents')) {
                        $objects = array_merge($objects, $result->get('Contents'));
                    }
                    if (count($objects)) {
                        $marker = $objects[count($objects) - 1]['Key'];
                    }
                } while ($result->get('IsTruncated') && ++$iteration < $maxIteration);
                if ($result->get('IsTruncated')) {
                    $errors[] = sprintf('The number of keys greater than %u, the first part is shown', count($objects));
                }
            } catch (S3Exception $e) {
                $errors[] = sprintf('Cannot retrieve objects: %s', $e->getMessage());
            }
        }

        return $this->twig->render(
            'list.html.twig',
            ['selected_bucket' => $bucket, 'buckets' => $buckets, 'objects' => $objects, 'errors' => $errors]
        );
    }
}
