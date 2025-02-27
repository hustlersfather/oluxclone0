<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cpanel extends Model
{
    // Specify the table name since the default pluralization does not match.
    protected $table = 'cpanel';

    // Define the primary key (if it's 'id', this line is optional).
    protected $primaryKey = 'id';

    // Define mass assignable fields (adjust as needed based on your schema).
    protected $fillable = ['seller_id', /* other fields */];

    /**
     * Optionally set an ID.
     * In Laravel you can access the primary key directly via $model->id.
     *
     * @param mixed $id
     * @return void
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * Optionally retrieve the ID.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Retrieve a list of Cpanel items joined with the associated user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getList()
    {
        return self::select('cpanel.*', 'user.*')
            ->join('user', 'user.id', '=', 'cpanel.seller_id')
            ->get();
    }

    // Optional helper methods for adding, updating, getting, and removing records.
    
    /**
     * Add a new Cpanel record.
     *
     * @param  array  $data
     * @return static
     */
    public static function add(array $data)
    {
        return self::create($data);
    }

    /**
     * Update this Cpanel record.
     *
     * @param  array  $data
     * @return bool
     */
    public function updateCpanel(array $data): bool
    {
        return $this->update($data);
    }

    /**
     * Retrieve a Cpanel record by ID.
     *
     * @param  mixed  $id
     * @return static|null
     */
    public static function getCpanel($id = null)
    {
        return $id ? self::find($id) : null;
    }

    /**
     * Remove a Cpanel record by ID.
     *
     * @param  mixed  $id
     * @return int  Number of records deleted
     */
    public static function removeCpanel($id): int
    {
        return self::destroy($id);
    }
}