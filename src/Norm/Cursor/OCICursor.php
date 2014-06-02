<?php

namespace Norm\Cursor;

use Norm\Norm;

class OCICursor extends \Norm\Cursor implements ICursor
{

    protected $collection;

    protected $dialect;

    protected $criteria;

    protected $raw;

    protected $sortBy;

    protected $limit = 0;

    protected $skip = 0;

    protected $match;

    protected $rows = null;

    protected $index = -1;

    public function __construct($collection)
    {
        $this->collection = $collection;

        $this->dialect = $collection->connection->getDialect();

        $this->raw = $collection->connection->getRaw();

        $this->criteria = $collection->criteria;
    }

    public function current()
    {
        if ($this->valid()) {
            return $this->rows[$this->index];
        }
    }

    public function getNext()
    {
        $this->next();
        return $this->current();
    }

    public function next()
    {

        if (is_null($this->rows)) {
            $this->execute();
        }

        $this->index++;
    }

    public function key()
    {
        return $this->index;
    }

    public function valid()
    {
        return isset($this->rows[$this->index]);
    }

    public function rewind()
    {
        $this->index = -1;
        $this->next();
    }

    public function count($foundOnly = false)
    {
        $wheres   = array();
        $data     = array();
        $matchOrs = array();
        $criteria = $this->prepareCriteria($this->criteria);

        if ($criteria) {
            foreach ($criteria as $key => $value) {
                $wheres[] = $this->dialect->grammarExpression($key, $value, $data);
            }
        }

        if (!is_null($this->match)) {
            $schema = $this->collection->schema();

            $i = 0;
            foreach ($schema as $key => $value) {
                if ($value instanceof \Norm\Schema\Reference) {
                    $matchOrs[] = $this->getQueryReference($i, $key, $value);
                } else {
                    $matchOrs[] = $key.' LIKE :m'.$i;
                    $i++;
                }
            }
            $wheres[] = '('.implode(' OR ', $matchOrs).')';
        }

        $query = "SELECT count(ROWNUM) r FROM " . $this->collection->name;

        if ($foundOnly) {
            if (!empty($wheres)) {
                $query .= ' WHERE '.implode(' AND ', $wheres);

                $statement = oci_parse($this->raw, $query);
                foreach ($data as $key => $value) {
                    oci_bind_by_name($statement, ':'.$key, $data[$key]);
                }
            } else {
                $statement = oci_parse($this->raw, $query);
            }
        } else {
            $statement = oci_parse($this->raw, $query);
        }

        $match = '';
        if ($foundOnly) {
            if ($matchOrs) {
                $match = '%'.$this->match.'%';

                foreach ($matchOrs as $key => $value) {
                    oci_bind_by_name($statement, ':m'.$key, $match);
                }
            }
        }

        oci_execute($statement);
        $result = array();
        while ($row = oci_fetch_array($statement, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS)) {
            $result[] = $row;
        }

        oci_free_statement($statement);

        $r = reset($result);
        $r = $r['R'];

        return (int) $r;
    }

    public function match($q)
    {
        $this->match = $q;
        return $this;
    }

    public function prepareCriteria($criteria)
    {
        if (is_null($criteria)) {
            $criteria = array();
        }

        if (array_key_exists('$id', $criteria)) {
            $criteria['id'] = $criteria['$id'];
            unset($criteria['$id']);
        }

        return $criteria ? : array();
    }

    public function execute()
    {

        $data = array();
        $matchOrs = array();
        $wheres = array();
        $order = '';
        $limit = '';
        $query = 'SELECT * FROM '.$this->collection->name;

        

        //fixme januar :  match and criteria match
        $criteria = $this->prepareCriteria($this->criteria);

        if ($criteria) {
            foreach ($criteria as $key => $value) {
                $wheres[] = $this->dialect->grammarExpression($key, $value, $data);
            }
        }

        if (!is_null($this->match)) {
            $i = 0;
            $schema = $this->collection->schema();
            foreach ($schema as $key => $value) {
                if ($value instanceof \Norm\Schema\Reference) {
                    $matchOrs[] = $this->getQueryReference($i, $key, $value);
                } else {
                    $matchOrs[] = $key.' LIKE :m'.$i;
                    $i++;
                }
            }
            $wheres[] = '('.implode(' OR ', $matchOrs).')';
        }

        if ($this->sortBy) {
            foreach ($this->sortBy as $key => $value) {
                if ($value == 1) {
                    $op = ' ASC';
                } else {
                    $op = ' DESC';
                }
                $order[] = $key . $op;
            }
            if (!empty($order)) {
                $order = ' ORDER BY '.implode(',', $order);
            }
        }

        if ($this->skip > 0) {
            $limit = 'r > '.($this->skip);

            if ($this->limit > 0) {
                $limit = ' WHERE r > '.$this->skip.' AND ROWNUM <= ' . $this->limit;
            }
        } elseif ($this->limit > 0) {
            $limit = ' WHERE ROWNUM <= ' . $this->limit;
        }

        if ($wheres) {
            $query .= ' WHERE '.implode(' AND ', $wheres);
        }

        $sql = 'SELECT * FROM (SELECT ROWNUM r, DATA.* FROM ('.$query.$order.') DATA )'.$limit;

        $statement = oci_parse($this->raw, $sql);
        foreach ($data as $key => $value) {
            oci_bind_by_name($statement, ':'.$key, $data[$key]);
        }

        if ($matchOrs) {
            $match = '%'.$this->match.'%';
            foreach ($matchOrs as $key => $value) {
                oci_bind_by_name($statement, ':m'.$key, $match);
            }
        }

        oci_execute($statement);

        // var_dump($query);
        // var_dump($data);


        $result = array();
        while ($row = oci_fetch_array($statement, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS)) {
            unset($row['R']);
            $result[] = $row;
        }
        $this->rows = $result;

        oci_free_statement($statement);
        $this->index = -1;
    }

    public function sort(array $fields)
    {
        $this->sortBy = $fields;
        return $this;
    }

    public function limit($num)
    {
        $this->limit = $num;
        return $this;
    }

    public function skip($offset)
    {
        $this->skip = $offset;
        return $this;
    }

    public function getQueryReference(&$i, $key,$schema)
    {
        // $model      = Norm::factory($foreign);
        // $refSchemes = $model->schema();
        
        $schema['foreignKey'] = $schema['foreignKey'] ?: 'id';

        if ($schema['foreignKey'] == '$id') {
            $schema['foreignKey'] = 'id';
        }

        if(!$schema['foreignGroup']){
            $query = $key .
            ' IN (SELECT '.$schema['foreignKey'].' FROM '.strtolower($schema['foreign']).' WHERE '.$schema['foreignLabel'].' LIKE :m'.$i.') ';
        }else{
            $query = $key .
            ' IN (SELECT '.$schema['foreignKey'].' FROM '.strtolower($schema['foreign']).' WHERE '.$schema['foreignLabel'].' LIKE :m'.$i.' AND groups =\''.$schema['foreignGroup'].'\') ';
            
        }
        
        $i++;

        return $query;
    }
}
