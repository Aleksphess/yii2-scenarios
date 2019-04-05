<?php

namespace indigerd\scenarios;

use yii\base\Model;
use yii\web\Request;
use indigerd\scenarios\exception\ModelValidateException;
use indigerd\scenarios\exception\RequestValidateException;
use indigerd\scenarios\validation\factory\ValidatorFactory;
use indigerd\scenarios\validation\factory\ValidatorCollectionFactory;

class Scenario
{
    protected $validatorFactory;

    protected $validatorCollectionFactory;

    protected $attributes = [];

    public function __construct(
        ValidatorFactory $validatorFactory,
        ValidatorCollectionFactory $validatorCollectionFactory,
        array $validationRules = []
    ) {
        $this->validatorFactory = $validatorFactory;
        $this->validatorCollectionFactory = $validatorCollectionFactory;
        foreach ($validationRules as $rule) {
            if (!is_array($rule) or sizeof($rule) < 2) {
                throw new \InvalidArgumentException('Invalid rule configuration');
            }
            $this->addValidationRule(...$rule);
        }
    }

    public function addValidationRule($attribute, $rule, array $params = []) : self
    {
        $attributes = is_array($attribute) ? $attribute : [$attribute];
        foreach ($attributes as $attributeName) {
            if (!isset($this->attributes[$attributeName])) {
                $this->attributes[$attributeName] = $this->validatorCollectionFactory->create();
            }
            $validator = $this->validatorFactory->create($rule, $params);
            $this->attributes[$attributeName]->addValidator($validator);
        }
        return $this;
    }

    public function validateModel(Model $model) : bool
    {
        $context = $model->toArray();
        foreach ($this->attributes as $attribute => $validatorCollection) {
            if (!$validatorCollection->validate($model->$attribute, $context)) {
                foreach ($validatorCollection->getMessages() as $message) {
                    $model->addError($attribute, $message);
                }
            }
        }
        if ($model->hasErrors()) {
            throw new ModelValidateException($model);
        }
        return true;
    }

    public function validateRequest(Request $request, string $variables = 'body') : bool
    {
        $errors = [];
        $context = ($variables == 'body' ? $request->getBodyParams() : $request->getQueryParams());
        foreach ($this->attributes as $attribute => $validatorCollection) {
            $value = isset($context[$attribute]) ? $context[$attribute] : null;
            if (!$validatorCollection->validate($value, $context)) {
                foreach ($validatorCollection->getMessages() as $message) {
                    $errors[$attribute][] = $message;
                }
            }
        }
        if (sizeof($errors) > 0) {
            throw new RequestValidateException($request, $errors);
        }
        return true;
    }
}