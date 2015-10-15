<?php
 namespace Norm\Connection;

use Exception;
use Norm\Model;
use Norm\Collection;
use Norm\Connection;
use Norm\Dialect\OracleDialect;
use Norm\Cursor\OCICursor as Cursor;

/**
 * OCI Connection.
 *
 * @author    Aprianto Pramana Putra <apriantopramanaputra@gmail.com>
 * @copyright 2013 PT Sagara Xinix Solusitama
 * @link      http://xinix.co.id/products/norm Norm
 * @license   https://raw.github.com/xinix-technology/norm/master/LICENSE
 */
class OCIConnection extends Connection
{
    protected $dialect;

    /**
     * Initializing class
     *
     * @param array $options
     *
     * @return void
     */
    public function initialize(array $options = array())
    {
        $defaultOptions = array(
            'username' => null,
            'password' => null,
            'dbname' => null,
            'charset' => null,
            'mode' => null
        );

        $this->options = array_merge($defaultOptions, $options);

        $this->raw = oci_connect(
            $this->options['username'],
            $this->options['password'],
            $this->options['dbname'],
            $this->options['charset'],
            $this->options['mode']
        );

        $this->prepareInit();

        $this->dialect = new OracleDialect($this);
    }

    /**
     * Preparing initialization of connection
     *
     * @return void
     */
    protected function prepareInit()
    {
        $stid = oci_parse($this->raw, "ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
        oci_execute($stid);
        oci_free_statement($stid);

        $stid = oci_parse($this->raw, "ALTER SESSION SET NLS_SORT = BINARY_CI");
        oci_execute($stid);
        oci_free_statement($stid);

        $stid = oci_parse($this->raw, "ALTER SESSION SET NLS_COMP = LINGUISTIC");
        oci_execute($stid);
        oci_free_statement($stid);
    }

    /**
     * {@inheritDoc}
     */
    public function query(Collection $collection)
    {
        return new Cursor($collection);
    }

    /**
     * Sync data to database. If it's new data, we insert it as new document,
     * otherwise, if the document exists, we just update it.
     *
     * @param Collection $collection
     * @param Model $model
     *
     * @return bool
     */
    public function save(Collection $collection, Model $model)
    {
        $collectionName = $collection->name;
        $data = $this->marshall($model->dump());
        $result = false;

        if (is_null($model->getId())) {
            $id = $this->insert($collectionName, $data);
            if ($id) {
                $model->setId($id);
                $result = true;
            }
        } else {
            $data['id'] = $model->getId();
            $result = $this->update($collectionName, $data);

            if ($result) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(Collection $collection, $model)
    {
        $collectionName = $collection->name;
        $id = $model->getId();

        $sql = 'DELETE FROM '.$collectionName.' WHERE id = :id';

        $stid = oci_parse($this->raw, $sql);
        oci_bind_by_name($stid, ":id", $id);
        $result = oci_execute($stid);
        oci_free_statement($stid);

        return $result;
    }

    /**
     * Perform insert new document to database.
     *
     * @param string $collectionName
     * @param mixed $data
     *
     * @return bool
     */
    public function insert($collectionName, $data)
    {
        $id = 0;
        $sql = $this->dialect->grammarInsert($collectionName, $data);

        $stid = oci_parse($this->raw, $sql);

        oci_bind_by_name($stid, ":id", $id);

        foreach ($data as $key => $value) {
            oci_bind_by_name($stid, ":".$key, $data[$key]);
        }

        oci_execute($stid);

        oci_free_statement($stid);

        return $id;
    }

    /**
     * Perform update to a document.
     *
     * @param string $collectionName
     * @param mixed $data
     *
     * @return bool
     */
    public function update($collectionName, $data)
    {
        $sql = $this->dialect->grammarUpdate($collectionName, $data);

        $stid = oci_parse($this->raw, $sql);

        oci_bind_by_name($stid, ":id", $data['id']);

        foreach ($data as $key => $value) {
            oci_bind_by_name($stid, ":".$key, $data[$key]);
        }

        $result = oci_execute($stid);

        oci_free_statement($stid);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function marshall($object)
    {
        if ($object instanceof \Norm\Type\DateTime) {
            return $object->format('Y-m-d H:i:s');
        } elseif (is_array($object)) {
            $result = array();
            foreach ($object as $key => $value) {
                if ($key[0] === '$') {
                    if ($key === '$id' || $key === '$type') {
                        continue;
                    } else {
                        $result[substr($key, 1)] = $this->marshall($value);
                    }
                } else {
                    $result[$key] = $this->marshall($value);
                }
            }
            return $result;
        } else {
            return parent::marshall($object);
        }
    }

    /**
     * Get dialect used by this implementation.
     *
     * @return \Norm\Dialect\OracleDialect
     */
    public function getDialect()
    {
        return $this->dialect;
    }
}
