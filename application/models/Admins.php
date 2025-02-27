<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    // Specify the table name if it does not follow the plural convention.
    protected $table = 'admin';

    // Define the primary key if it’s not 'id'.
    protected $primaryKey = 'id';

    // Set whether the model should manage timestamps.
    public $timestamps = false;

    // Attributes that are mass assignable.
    protected $fillable = ['login', 'password'];

    /**
     * Check if the provided login credentials are valid.
     *
     * @param  string  $login
     * @param  string  $password
     * @return bool
     */
    public static function checkLogin(string $login, string $password): bool
    {
        // Using SHA-256 as in your original code.
        $hash = hash('sha256', $password);
        return self::where('login', $login)
                   ->where('password', $hash)
                   ->exists();
    }

    /**
     * Set the admin ID.
     *
     * @param  mixed  $id
     * @return void
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * Get the admin ID.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Add a new admin record.
     * (Assuming $data contains 'login' and 'password', etc.)
     *
     * @param  array  $data
     * @return static
     */
    public static function add(array $data)
    {
        // You might consider hashing the password before saving.
        $data['password'] = hash('sha256', $data['password']);
        return self::create($data);
    }

    /**
     * Update this admin record.
     *
     * @param  array  $data
     * @return bool
     */
    public function updateAdmin(array $data): bool
    {
        // If updating the password, hash it first.
        if (isset($data['password'])) {
            $data['password'] = hash('sha256', $data['password']);
        }
        return $this->update($data);
    }

    /**
     * Retrieve an admin record by ID.
     *
     * @param  mixed  $id
     * @return static|null
     */
    public static function getAdmin($id)
    {
        return self::find($id);
    }

    /**
     * Remove an admin record by ID.
     *
     * @param  mixed  $id
     * @return int  Number of records deleted
     */
    public static function removeAdmin($id): int
    {
        return self::destroy($id);
    }
}