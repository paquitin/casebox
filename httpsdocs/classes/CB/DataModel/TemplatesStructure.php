<?php
namespace CB\DataModel;

use CB\DB;
use CB\Util;

class TemplatesStructure extends Base
{
    /**
     * database table name
     * @var string
     */
    protected static $tableName = 'templates_structure';

    /**
     * available table fields
     *
     * associative array of fieldName => type
     * that is also used for trivial validation of input values
     *
     * @var array
     */
    protected static $tableFields = array(
        'id' => 'int'
        ,'pid' => 'int'
        ,'template_id' => 'int'
        // ,'tag' => 'varchar' //obsolete
        ,'level' => 'int'
        ,'name' => 'varchar'
        ,'type' => 'varchar'
        ,'order' => 'int'
        ,'cfg' => 'text'
        ,'solr_column_name' => 'varchar'
    );

    protected static $decodeJsonFields = array('cfg');

    /**
     * get only active (not deleted fields) for given template
     * @param  int   $templateId optional, filter by a template
     * @param  bool  $onlyActive to return only active (nit deleted fields)
     * @return array
     */
    public static function getFields($templateId = false, $onlyActive = true)
    {
        $rez = array();

        $sql = 'SELECT
                ts.id
                ,ts.pid
                ,ts.template_id
                ,ts.name
                ,ts.`level`
                ,ts.`type`
                ,ts.cfg
                ,ts.order
                ,ts.solr_column_name
                ,o.data

            FROM templates_structure ts
            LEFT JOIN objects o ON ts.id = o.id ';

        if ($onlyActive) {
            $sql .= 'JOIN tree t on ts.id = t.id AND t.dstatus = 0 ';
        }

        if (is_numeric($templateId)) {
            $sql .= 'WHERE ts.template_id = $1 ';
        }

        $sql .= 'ORDER BY ts.template_id, ts.`order` ';

        $res = DB\dbQuery($sql, $templateId);

        while ($r = $res->fetch_assoc()) {
            $data = Util\toJSONArray($r['data']);
            unset($r['data']);

            //overwrite fields from templates table with values from objects.data
            $r = array_merge($r, $data);
            $r['cfg'] = Util\toJSONArray($r['cfg']);

            $r['title'] = Util\detectTitle($r);

            $rez[] = static::replaceBackwardCompatibleFieldOptions($r);
        }
        $res->close();

        return $rez;
    }

    public static function replaceBackwardCompatibleFieldOptions($f)
    {
        if (!empty($f['cfg']['showIn'])) {
            if ($f['cfg']['showIn'] == 'tabsheet') {
                $f['cfg']['editMode'] = 'standalone';
            }

            unset($f['cfg']['showIn']);
        }

        /*switch ($f['type']) {
            case 'checkbox':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'yesno';
                break;

            case 'iconcombo':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'templatesIconSet';
                break;

            case '_language':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'languages';
                break;

            case '_sex':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'sex';
                break;

            case '_short_date_format':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'shortDateFormats';
                break;

            case '_fieldTypesCombo':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'fieldTypes';
                break;

            case '_templateTypesCombo':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'templateTypes';
                break;

            case 'timeunits':
                $f['type'] = 'combo';
                $f['cfg']['source'] = 'timeUnits';
                break;
        }/**/

        return $f;
    }

    public static function copy($sourceId, $targetId)
    {
        //detect target template
        $r = Tree::read($targetId);

        $tsr = static::read($r['pid']);

        $parentTemplate = empty($tsr)
            ? $r['pid']
            : $r['template_id'];

        //copying record
        DB\dbQuery(
            'INSERT INTO `templates_structure`
                (`id`
                ,`pid`
                ,`template_id`
                ,`name`
                ,`l1`
                ,`l2`
                ,`l3`
                ,`l4`
                ,`type`
                ,`order`
                ,`cfg`
                ,`solr_column_name`
                )
            SELECT
                t.id
                ,t.pid
                ,$3
                ,ts.name
                ,ts.l1
                ,ts.l2
                ,ts.l3
                ,ts.l4
                ,ts.type
                ,ts.order
                ,ts.cfg
                ,ts.solr_column_name
            FROM `tree` t
                ,templates_structure ts
            WHERE t.id = $2
                AND ts.id = $1
            ON DUPLICATE KEY UPDATE
                pid = t.pid
                ,template_id = $3
                ,name = ts.name
                ,l1 = ts.l1
                ,l2 = ts.l2
                ,l3 = ts.l3
                ,l4 = ts.l4
                ,`type` = ts.type
                ,`order` = ts.order
                ,`cfg` = ts.cfg
                ,solr_column_name = ts.solr_column_name',
            array(
                $sourceId
                ,$targetId
                ,$parentTemplate
            )
        );
    }

    public static function move($sourceId, $targetId)
    {
        DB\dbQuery(
            'UPDATE templates_structure
            SET pid = $2
            WHERE id = $1',
            array(
                $sourceId
                ,$targetId
            )
        );
    }
}
