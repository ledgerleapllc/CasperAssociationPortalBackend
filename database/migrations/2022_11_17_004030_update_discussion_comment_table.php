<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDiscussionCommentTable extends Migration {
    public function up() {
        Schema::table('discussion_comments', function ($table) {
        	$table->timestamp('edited_at')->nullable();
        	$table->timestamp('deleted_at')->nullable();
        });
    }
	public function down() {
        //
    }
}
