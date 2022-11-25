<?php

namespace App\Repositories\Base;
use Exception;

abstract class BaseRepository
{
    protected $model;

    public function __construct()
    {
        $this->setModel();
    }

    abstract public function getModel();


    public function setModel()
    {
        $this->model = app()->make(
            $this->getModel()
        );
    }

    public function getAll()
    {
        return $this->model->all();
    }


    public function find($id)
    {
        $result = $this->model->find($id);

        return $result;
    }

    public function create($attributes)
    {
        return $this->model->create($attributes);
    }

    public function update($id, array $attributes)
    {
        $result = $this->find($id);
        if ($result) {
            try {
                $result->update($attributes);
                return $result;
            } catch (\Exception $exception) {
                return false;
            }
        }
        return false;
    }

    /**
     * Get first data.
     *
     * @param array  $where       [array where]
     * @param string $columnOrder [default is created_at]
     * @param string $orderType   [default is DESC]
     *
     * @return object
     */
    public function first($where = [], $columnOrder = 'created_at', $orderType = 'DESC')
    {
        return $this->model->where($where)->orderBy($columnOrder, $orderType)->first();
    }

    /**
     * Update data.
     *
     * @param array $attributes [array input]
     * @param array $where      [array input]
     *
     * @return object
     */
    public function updateConditions(array $attributes, $where)
    {
        $data = null;
        try {
            $data = $this->model->where($where)->update($attributes);
            return $data;
        } catch (Exception $e) {
            throw $e;
        }
    }

     /**
     * Create or update a record matching the attributes, and fill it with values.
     *
     * @param array $attributes [attributes]
     * @param array $values     [values]
     *
     * @return static
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        try {
            return $this->model->updateOrCreate($attributes, $values);
        } catch (Exception $e) {
            throw $e;
        }
    }

     /**
     * Retrieve the result of the query. However, if no result is found a ModelNotFoundException will be thrown.
     *
     * @param array $where      [condition query]
     * @param array $attributes [values to select]
     *
     * @return Object
     */
    public function firstOrFail(array $where, $attributes = ['*'])
    {
        return $this->model->select($attributes)->where($where)->firstOrFail();
    }

     /**
     * Create or update with Trashed a record matching the attributes, and fill it with values.
     *
     * @param array $attributes [attributes]
     * @param array $values     [values]
     *
     * @return static
     */
    public function updateOrCreateWithTrashed(array $attributes, array $values = [])
    {
        try {
            return $this->model->withTrashed()->updateOrCreate($attributes, $values);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param array|int $id [id item remove]
     *
     * @return int
     */
    public function delete($id)
    {
        try {
            $data = $this->model->destroy($id);
            return $data;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Delete data.
     *
     * @param array $where      [array input]
     *
     * @return object
     */
    public function deleteConditions(array $where)
    {
        $data = null;
        try {
            $data = $this->model->where($where)->delete();
            return $data;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function firstOrCreate(array $attributes, array $values = [])
    {
        try {
            return $this->model->firstOrCreate($attributes, $values);
        } catch (Exception $e) {
            throw $e;
        }
    }
}