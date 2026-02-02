<?php


class MenuItem extends \Illuminate\Database\Eloquent\Model
{
    use \Aimeos\Nestedset\NodeTrait;

    public $timestamps = false;

    protected $fillable = ['menu_id','parent_id'];

    public static function resetActionsPerformed()
    {
        static::$actionsPerformed = 0;
    }

    protected function getScopeAttributes()
    {
        return ['menu_id'];
    }

}
