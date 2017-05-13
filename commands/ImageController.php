<?php


namespace app\commands;


use app\models\Image;
use yii\console\Controller;
use yii\helpers\Console;

class ImageController extends Controller
{
    public function actionGenerateAll()
    {
        $batchSize = 100;
        $batch = Image::find()->batch($batchSize);
        foreach ($batch as $images) {
            if (function_exists('pcntl_fork')) {
                $this->processImagesInParallel($images);
            } else {
                $this->processImagesOneByOne($images);
            }
        }
    }

    private function processImagesOneByOne($images)
    {
        foreach ($images as $i => $image) {
            /** @var Image $image */
            $this->generateThumbs($image);
        }
    }

    private function processImagesInParallel($images)
    {
        $childPids = [];

        foreach ($images as $i => $image) {
            /** @var Image $image */
            $newPid = pcntl_fork();
            if ($newPid == -1) {
                die('Can\'t fork');
            }

            if ($newPid) {
                $childPids[] = $newPid;
            } else {
                \Yii::$app->db->pdo = null;
                $this->generateThumbs($image);
                exit(0);
            }
        }

        foreach ($childPids as $childPid) {
            pcntl_waitpid($childPid, $status);
        }
    }

    public function actionGenerate($id)
    {
        $this->generateThumbs(Image::findOne(['id' => $id]));
    }

    public function actionGenerateForProject($id)
    {
        $images = Image::find()->where(['project_id' => $id])->all();
        foreach ($images as $image) {
            /* @var Image $image */
            $this->generateThumbs($image);
        }
    }

    protected function generateThumbs(Image $image)
    {
        echo 'Generating image #' . $image->id . ' for project #' . $image->project_id;

        $image->generateFull();
        $image->generateThumbnail();
        $image->generateBigThumbnail();

        echo Console::renderColoredString(" %Gdone.%n\n");
    }
}
