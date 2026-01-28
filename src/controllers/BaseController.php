<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\controllers;

use Yii;
use yii\rest\Controller;

/**
 * Base controller for anonymizer module REST endpoints
 *
 * Provides CORS/OPTIONS handling for preflight requests.
 *
 * @package dmstr\anonymizer\controllers
 */
abstract class BaseController extends Controller
{
    /**
     * @var array Allowed HTTP methods
     */
    protected array $allowedVerbs = ['GET', 'POST', 'DELETE', 'OPTIONS'];

    /**
     * Handle preflight OPTIONS requests
     *
     * @see https://www.yiiframework.com/doc/guide/2.0/en/rest-controllers#cors
     * @return bool
     */
    public function actionOptions(): bool
    {
        $headers = Yii::$app->getResponse()->getHeaders();
        $headers->set('Allow', implode(', ', $this->allowedVerbs));
        $headers->set('Access-Control-Allow-Methods', implode(', ', $this->allowedVerbs));
        return true;
    }

    /**
     * Map OPTIONS requests to actionOptions for preflight handling
     *
     * @param string $id The action ID
     * @return \yii\base\Action|null
     */
    public function createAction($id)
    {
        if (Yii::$app->getRequest()->getMethod() === 'OPTIONS') {
            $id = 'options';
        }
        return parent::createAction($id);
    }
}
