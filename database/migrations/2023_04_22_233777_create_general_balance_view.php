<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \DB::statement($this->createView());
    }
   
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \DB::statement($this->dropView());
    }

     /**
     * Reverse the migrations.
     *
     * @return void
     */
    private function createView(): string
    {
        return "
            CREATE OR REPLACE VIEW general_balance AS
            with get_amount as (
                SELECT code , if(a.type_id = 6 , 0, a.init_amount) as init_amount, (select ifnull(sum(amount), 0) from movements where account_id = a.id) as balance from accounts a
                join currencies b on (a.badge_id = b.id)
                where a.user_id = user_id()
            ), sum_amount as (
                select code as currency, sum(init_amount + balance) as balance from get_amount 
                group by code
                order by sum(init_amount + balance) desc
            )
            SELECT * from sum_amount
            ";
    }
   
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    private function dropView(): string
    {
        return "DROP VIEW IF EXISTS `general_balance`";
    }
};
