<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create all legacy aula tables for tenant databases.
 * Generated from database.sql dump.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // au_activitylog
        Schema::create('au_activitylog', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('type')->nullable()->comment('Which type of activity (i.e. 1=login, 2=logout, 3=vote, 4= new idea etc.)');
            $table->string('info', 1024)->nullable()->comment('comment or activity as clear text');
            $table->integer('group')->nullable()->comment('group type of user that triggered the activity');
            $table->integer('target')->default(0)->comment('target of the activity (i.E. vote for a specific idea id)');
            $table->dateTime('created')->nullable()->comment('Date time of the activity');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update is saved if dataset is altered');
        });

        // au_categories
        Schema::create('au_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 1024)->nullable()->comment('name of category');
            $table->text('description_public')->nullable()->comment('public description, seen in frontend');
            $table->text('description_internal')->nullable()->comment('only seen by admins');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active 2=archived');
            $table->dateTime('created')->nullable()->comment('create date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
            $table->string('hash_id', 1024)->nullable()->comment('hash id of the category');
        });

        // au_change_password
        Schema::create('au_change_password', function (Blueprint $table) {
            $table->integer('user_id')->nullable();
            $table->text('secret')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        // au_commands
        Schema::create('au_commands', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cmd_id')->nullable()->comment('command id (i.e. 1=delete user, 2=suspend user, 3=unsuspend user 4=vacation_on, 5=vacation_off etc.)');
            $table->string('command', 1024)->nullable()->comment('command in text form');
            $table->string('parameters', 2048)->nullable()->comment('parameters for the command');
            $table->dateTime('date_start')->nullable()->comment('Date and time, when command is executed');
            $table->dateTime('date_end')->nullable()->comment('Date and time, when command execution ends');
            $table->boolean('active')->nullable()->comment('0=inactive, 1=active');
            $table->integer('status')->nullable()->comment('0=not executed yet, 1=executed, 2=executed with error');
            $table->string('info', 1024)->nullable()->comment('contains comment of person that entered command');
            $table->integer('target_id')->nullable()->comment('id of target that the command relates to - i.E. user id, group id, organisation');
            $table->integer('creator_id')->nullable()->comment('id of user who created the command');
            $table->dateTime('created')->nullable()->comment('create date of the command');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of command');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
        });

        // au_comments
        Schema::create('au_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('content', 4096)->nullable()->comment('content of the comment');
            $table->integer('sum_likes')->nullable()->comment('count of likes for faster access and less DB queries');
            $table->integer('user_id')->nullable()->comment('id of user that created comment');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, 2=suspended, 3=reported, 4=archived');
            $table->dateTime('created')->nullable()->comment('datetime of creation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of comment');
            $table->integer('updater_id')->nullable()->comment('user_id of the updater');
            $table->string('hash_id', 1024)->nullable()->comment('hash id of the comment');
            $table->integer('language_id')->nullable()->comment('Language_id');
            $table->integer('idea_id')->nullable()->comment('id of the idea');
            $table->integer('parent_id')->nullable()->comment('id of the parent comment (0=first comment)');
        });

        // au_consent
        Schema::create('au_consent', function (Blueprint $table) {
            $table->integer('user_id')->comment('id of user');
            $table->integer('text_id')->default(0)->comment('id of text');
            $table->boolean('consent')->default(false)->comment('1= user consented 0= user didnt consent 2=user revoked consent');
            $table->dateTime('date_consent')->nullable()->comment('date of consent to terms');
            $table->dateTime('date_revoke')->nullable()->comment('date of revocation');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
            $table->integer('status')->default(1)->comment('status of consent');
            $table->primary(['user_id', 'text_id']);
        });

        // au_delegation
        Schema::create('au_delegation', function (Blueprint $table) {
            $table->integer('user_id_original')->comment('original user (delegating)');
            $table->integer('user_id_target')->comment('receiving user');
            $table->integer('room_id')->default(0)->comment('id where the delegation is in');
            $table->integer('topic_id')->comment('id of the topic the delegation is for');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, 2=suspended');
            $table->integer('updater_id')->default(0)->comment('id of the updating user');
            $table->dateTime('created')->nullable()->comment('created date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->primary(['user_id_original', 'user_id_target', 'topic_id']);
        });

        // au_groups
        Schema::create('au_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('group_name', 1024)->nullable()->comment('name of group');
            $table->text('description_public')->nullable()->comment('public description of group');
            $table->text('description_internal')->nullable()->comment('internal description, only seen by admins');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, 2=suspended, 3=archived');
            $table->string('internal_info', 2048)->nullable()->comment('internal info, only visible to admins');
            $table->dateTime('created')->nullable()->comment('datetime of creation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of group');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
            $table->string('hash_id', 1024)->nullable()->comment('hash id of the group');
            $table->string('access_code', 1024)->nullable()->comment('if set then access code is needed to join group');
            $table->integer('order_importance')->nullable()->comment('order that groups are shown (used for display)');
            $table->integer('vote_bias')->nullable()->comment('votes weight per user in this group');
        });

        // au_ideas
        Schema::create('au_ideas', function (Blueprint $table) {
            $table->increments('id');
            $table->text('title')->nullable();
            $table->text('content')->nullable()->comment('content of the idea');
            $table->integer('sum_likes')->nullable()->comment('aggregated likes for faster access, less DB Queries');
            $table->integer('sum_votes')->nullable()->comment('aggregated votes for faster access, less DB Queries');
            $table->integer('number_of_votes')->nullable()->comment('number of votes given for this idea');
            $table->integer('user_id')->nullable()->comment('creator id');
            $table->integer('votes_available_per_user')->nullable()->comment('number of votes that are available per user');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review');
            $table->integer('language_id')->default(0)->comment('id of the language 0=default');
            $table->dateTime('created')->nullable()->comment('create date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of idea');
            $table->string('hash_id', 1024)->nullable()->comment('hash id for this id');
            $table->integer('order_importance')->nullable()->comment('order of appearance / importance');
            $table->text('info')->nullable()->comment('free text field, can be used to add extra information');
            $table->integer('updater_id')->nullable()->comment('id of the updater');
            $table->integer('room_id')->nullable()->comment('id of the room');
            $table->integer('is_winner')->nullable()->comment('flag that this idea succeeded in the voting phase');
            $table->integer('approved')->nullable()->comment('approved in approval phase');
            $table->text('approval_comment')->nullable()->comment('comment or reasoning why an idea was disapproved / approved');
            $table->integer('topic_id')->nullable()->comment('id of topic that idea belongs to');
            $table->integer('sum_comments')->default(0);
            $table->text('custom_field1')->nullable()->comment('custom_field1');
            $table->text('custom_field2')->nullable()->comment('custom_field2');
            $table->integer('type')->default(0)->comment('type of idea 0=std 1=school induced (i.e.survey)');
        });

        // au_likes
        Schema::create('au_likes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable()->comment('id of liking user');
            $table->integer('object_id')->nullable()->comment('id of liked object (idea or comment)');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, temporarily 2=suspended');
            $table->dateTime('created')->nullable()->comment('create date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('update date');
            $table->string('hash_id', 1024)->nullable()->comment('hash id of the like');
            $table->integer('object_type')->nullable()->comment('type of liked object 1=idea, 2=comment');
        });

        // au_media
        Schema::create('au_media', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('type')->nullable()->comment('type of media (1=picture, 2=video, 3= audio 4=pdf etc.)');
            $table->string('url', 2048)->nullable()->comment('URL to media');
            $table->integer('system_type')->nullable()->comment('0=default, 1=custom');
            $table->string('path', 2048)->nullable()->comment('system path to the file');
            $table->boolean('status')->nullable()->comment('0=inactive, 1=active 2= reported 3=archived');
            $table->string('info', 2028)->nullable()->comment('description');
            $table->string('name', 1024)->nullable()->comment('name of medium (other than filename)');
            $table->string('filename', 2048)->nullable()->comment('filename with extension (without path)');
            $table->dateTime('created')->nullable()->comment('creation date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->string('hash_id', 1024)->nullable()->comment('hash_id of the media');
            $table->integer('updater_id')->nullable()->comment('id of the user that uploaded');
        });

        // au_messages
        Schema::create('au_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('creator_id')->nullable()->comment('user id of the creator (0=system)');
            $table->string('headline', 1024)->nullable()->comment('headline of the news');
            $table->text('body')->nullable()->comment('news body');
            $table->dateTime('publish_date')->nullable()->comment('date, when the news are published to the dashboards');
            $table->integer('target_group')->nullable()->comment('defines group that should receive the news (0=all or group id)');
            $table->integer('target_id')->nullable()->comment('user_id of user that should receive the message');
            $table->integer('status')->nullable()->comment('0=inactive 1=active 2=archive');
            $table->boolean('only_on_dashboard')->nullable()->comment('0=no 1= only displayed on dashboard, no active sending');
            $table->dateTime('created')->nullable()->comment('date when news were created');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
            $table->string('hash_id', 1024)->nullable()->comment('hash_id for news post');
            $table->integer('language_id')->nullable()->comment('id of language 0=default');
            $table->integer('level_of_detail')->nullable()->comment('enables the user to filter msgs');
            $table->integer('msg_type')->nullable()->comment('type id of a msg 1=general news 2=user specific news, 3=idea news etc.');
            $table->integer('room_id')->nullable()->comment('if specified only displayed to room members');
            $table->integer('pin_to_top')->default(0)->comment('0=no, 1 = yes');
        });

        // au_phases_global_config
        Schema::create('au_phases_global_config', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 1024)->nullable()->comment('name of phase');
            $table->integer('phase_id')->nullable()->comment('0=wild idea 10=workphase 20=approval 30=voting 40=implementation');
            $table->integer('duration')->nullable()->comment('default duration of phase');
            $table->integer('time_scale')->nullable()->comment('timescale of default duration (0=hours, 1=days, 2=months)');
            $table->string('description_public', 4096)->nullable()->comment('public description of phase');
            $table->string('description_internal', 4096)->nullable()->comment('description only seen by admins');
            $table->boolean('status')->default(false)->comment('0=inactive, 1=active');
            $table->integer('type')->nullable()->comment('phase type, 0=voting enabled, 1=voting+likes enabled, 2=likes enabled, 3=no votes, no likes etc.)');
            $table->dateTime('created')->nullable()->comment('time of creation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('time of last update');
            $table->integer('updater_id')->nullable();
        });

        // au_rel_categories_ideas
        Schema::create('au_rel_categories_ideas', function (Blueprint $table) {
            $table->integer('category_id')->comment('id of category');
            $table->integer('idea_id')->comment('id of idea');
            $table->dateTime('created')->nullable()->comment('creation time of relation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of dataset');
            $table->integer('updater_id')->nullable();
            $table->primary(['category_id', 'idea_id']);
        });

        // au_rel_categories_media
        Schema::create('au_rel_categories_media', function (Blueprint $table) {
            $table->integer('category_id')->comment('id of category');
            $table->integer('media_id')->comment('id of media in mediatable');
            $table->integer('type')->nullable()->comment('position where media is used within category');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable();
            $table->primary(['category_id', 'media_id']);
        });

        // au_rel_categories_rooms
        Schema::create('au_rel_categories_rooms', function (Blueprint $table) {
            $table->integer('category_id')->comment('id of category');
            $table->integer('room_id')->comment('id of room');
            $table->dateTime('created')->nullable()->comment('creation time of relation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of dataset');
            $table->integer('updater_id')->nullable()->comment('id of updater');
            $table->primary(['category_id', 'room_id']);
        });

        // au_rel_groups_media
        Schema::create('au_rel_groups_media', function (Blueprint $table) {
            $table->integer('group_id')->comment('id of group');
            $table->integer('media_id')->comment('id of media');
            $table->integer('type')->nullable()->comment('position of media within group');
            $table->integer('status')->nullable()->comment('0=inactive 1=active');
            $table->dateTime('created')->nullable();
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate();
            $table->integer('updater_id')->nullable();
            $table->primary(['group_id', 'media_id']);
        });

        // au_rel_groups_users
        Schema::create('au_rel_groups_users', function (Blueprint $table) {
            $table->integer('group_id')->comment('group id');
            $table->integer('user_id')->comment('id of user');
            $table->integer('status')->nullable()->comment('0=inactive 1=active 2=suspended 3=archive');
            $table->dateTime('created')->nullable()->comment('creation time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('id of the user who did the update');
            $table->primary(['group_id', 'user_id']);
        });

        // au_rel_ideas_comments
        Schema::create('au_rel_ideas_comments', function (Blueprint $table) {
            $table->integer('idea_id')->comment('id of the idea');
            $table->integer('comment_id')->comment('id of the comment');
            $table->integer('status')->nullable()->comment('0=inactive 1=active 2=suspended 3=archive');
            $table->dateTime('created')->nullable()->comment('time of creation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of dataset');
            $table->integer('updater_id')->nullable();
            $table->primary(['idea_id', 'comment_id']);
        });

        // au_rel_ideas_media
        Schema::create('au_rel_ideas_media', function (Blueprint $table) {
            $table->integer('idea_id');
            $table->integer('media_id');
            $table->dateTime('created')->nullable();
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate();
            $table->integer('updater_id')->nullable();
            $table->primary(['idea_id', 'media_id']);
        });

        // au_rel_rooms_media
        Schema::create('au_rel_rooms_media', function (Blueprint $table) {
            $table->integer('room_id')->comment('id of the room');
            $table->integer('media_id')->comment('id of the medium in media table');
            $table->integer('type')->nullable()->comment('position within the room');
            $table->integer('status')->nullable()->comment('0=inactive 1=active');
            $table->dateTime('created')->nullable();
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate();
            $table->integer('updater_id')->nullable();
            $table->primary(['room_id', 'media_id']);
        });

        // au_rel_rooms_users
        Schema::create('au_rel_rooms_users', function (Blueprint $table) {
            $table->integer('room_id')->comment('id of the room');
            $table->integer('user_id')->comment('id of the user');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, 2= temporarily suspended, 3= historic');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of updater');
            $table->primary(['room_id', 'user_id']);
        });

        // au_rel_topics_ideas
        Schema::create('au_rel_topics_ideas', function (Blueprint $table) {
            $table->integer('topic_id')->comment('id of the topic');
            $table->integer('idea_id')->comment('id of the idea');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->dateTime('created')->nullable();
            $table->integer('updater_id')->nullable();
            $table->primary(['topic_id', 'idea_id']);
        });

        // au_rel_topics_media
        Schema::create('au_rel_topics_media', function (Blueprint $table) {
            $table->integer('topic_id')->comment('id of the topic');
            $table->integer('media_id')->comment('id of the media in media table');
            $table->integer('type')->nullable()->comment('position within the topic');
            $table->dateTime('created')->nullable()->comment('creation date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable();
            $table->primary(['topic_id', 'media_id']);
        });

        // au_rel_user_user
        Schema::create('au_rel_user_user', function (Blueprint $table) {
            $table->integer('user_id1')->comment('id of first user');
            $table->integer('user_id2')->comment('id of second user');
            $table->integer('type')->nullable()->comment('type of relation 0=associated 1=associated and following / subscribed');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, 2=suspended 3= archived');
            $table->dateTime('created')->nullable()->comment('create date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('id of user who did the update');
            $table->primary(['user_id1', 'user_id2']);
        });

        // au_rel_users_media
        Schema::create('au_rel_users_media', function (Blueprint $table) {
            $table->integer('user_id')->comment('id of the user');
            $table->integer('media_id')->comment('id of the media in the media table');
            $table->integer('type')->nullable()->comment('position within the user');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable();
            $table->primary(['user_id', 'media_id']);
        });

        // au_rel_users_triggers
        Schema::create('au_rel_users_triggers', function (Blueprint $table) {
            $table->integer('user_id')->comment('id of the user');
            $table->integer('trigger_id')->comment('id of the trigger');
            $table->boolean('user_consent')->nullable()->comment('0=no 1=yes');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
            $table->primary(['user_id', 'trigger_id']);
        });

        // au_reported
        Schema::create('au_reported', function (Blueprint $table) {
            $table->integer('user_id')->comment('id of the reporting user');
            $table->integer('type')->comment('type of reported object 0=idea, 1=comment, 2=user');
            $table->integer('object_id')->comment('id of reported object');
            $table->integer('status')->nullable()->comment('0=new 1=acknowledged by admin');
            $table->dateTime('created')->nullable()->comment('create date');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->text('reason')->nullable()->comment('reason for reporting');
            $table->text('internal_info')->nullable()->comment('internal notes on this reporting');
            $table->primary(['user_id', 'object_id', 'type']);
        });

        // au_roles
        Schema::create('au_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('name')->nullable()->comment('name of role');
            $table->text('description_public')->nullable()->comment('description useable in frontend');
            $table->text('description_internal')->nullable()->comment('description only seen by admins');
            $table->integer('order')->nullable()->comment('used for sorting in display in frontend');
            $table->integer('rights_level')->nullable()->comment('0=view_only, 10=std_user, 20=privileged user1, 30=privileged user 2, 40=privileged user 5, 50=admin, 60=tech admin');
            $table->boolean('status')->nullable()->comment('0=inactive, 1=active 2=suspended 3=archived');
            $table->dateTime('created')->nullable()->comment('time of creation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of dataset');
            $table->string('hash_id', 1024)->nullable()->comment('hash id of the role');
            $table->integer('updater_id')->nullable();
        });

        // au_rooms
        Schema::create('au_rooms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('room_name', 1024)->nullable()->comment('Name of the room');
            $table->text('description_public')->nullable()->comment('public description of the room');
            $table->text('description_internal')->nullable()->comment('info, only visible to admins');
            $table->integer('status')->nullable()->comment('0=inactive 1=active 2= suspended, 3=archived');
            $table->boolean('restrict_to_roomusers_only')->nullable()->comment('1=yes, only users that are part of this room can view and vote');
            $table->integer('order_importance')->nullable()->comment('order - useable for display purposes or logical operations');
            $table->dateTime('created')->nullable()->comment('Date time when room was created');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('Last update of room');
            $table->integer('updater_id')->nullable()->comment('user_id of the updater');
            $table->string('hash_id', 1024)->nullable()->comment('hashed id of the room');
            $table->string('access_code', 1024)->nullable()->comment('if set, user needs access code to access room');
            $table->text('internal_info')->nullable()->comment('internal info and notes on this room');
            $table->integer('phase_duration_0')->nullable()->comment('phase duration 0');
            $table->integer('phase_duration_1')->nullable()->comment('phase_duration_1');
            $table->integer('phase_duration_2')->nullable()->comment('phase_duration_2');
            $table->integer('phase_duration_3')->nullable()->comment('phase_duration_3');
            $table->integer('phase_duration_4')->nullable()->comment('phase_duration_4');
            $table->integer('type')->default(0)->comment('0 = standard room 1 = MAIN ROOM (aula)');
        });

        // au_services
        Schema::create('au_services', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 1024)->nullable()->comment('name of the service');
            $table->integer('type')->nullable()->comment('type of service (0=authentication, 1=push notification etc.)');
            $table->text('url')->nullable()->comment('URL to service');
            $table->text('return_url')->nullable()->comment('return url to main system');
            $table->string('api_secret', 4096)->nullable()->comment('secret used for service');
            $table->text('api_key')->nullable()->comment('public key used');
            $table->text('api_tok')->nullable()->comment('token for api if necessary');
            $table->text('parameter1')->nullable()->comment('miscellaneous parameter');
            $table->text('parameter2')->nullable()->comment('miscellaneous parameter');
            $table->text('parameter3')->nullable()->comment('miscellaneous parameter');
            $table->text('parameter4')->nullable()->comment('miscellaneous parameter');
            $table->text('parameter5')->nullable()->comment('miscellaneous parameter');
            $table->text('parameter6')->nullable()->comment('miscellaneous parameter');
            $table->text('description_public')->nullable()->comment('Description for public view');
            $table->text('description_internal')->nullable()->comment('Description for internal view only');
            $table->boolean('status')->nullable()->comment('0=inactive, 1=active');
            $table->integer('order')->nullable()->comment('order for frontend display');
            $table->dateTime('created')->nullable()->comment('time of creation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
            $table->string('hash_id', 1024)->nullable()->comment('hash_id of the service');
        });

        // au_system_current_state
        Schema::create('au_system_current_state', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('online_mode')->nullable()->comment('0=off, 1=on, 2=off (weekend) 3=off (vacation) 4=off (holiday)');
            $table->boolean('revert_to_on_active')->nullable()->comment('auto turn back on active (1) or inactive (0)');
            $table->dateTime('revert_to_on_date')->nullable()->comment('date and time, when system gets back online');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable();
        });

        // au_system_global_config
        Schema::create('au_system_global_config', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 1024)->nullable()->comment('name of organisation');
            $table->string('internal_hash_id', 2048)->nullable()->comment('hash id within the organisation');
            $table->string('external_hash_id', 2048)->nullable()->comment('hash id of the organisation to the outside world');
            $table->text('description_public')->nullable()->comment('text that is publicly displayed on the frontend');
            $table->string('base_url', 2048)->nullable()->comment('base url of the organisation instance');
            $table->string('media_url', 2048)->nullable()->comment('url for media contents');
            $table->integer('preferred_language')->nullable()->comment('id for the default language');
            $table->integer('date_format')->nullable()->comment('id for the date format');
            $table->integer('time_format')->nullable()->comment('id for the time format');
            $table->integer('first_workday_week')->nullable()->comment('id for the first workday');
            $table->integer('last_workday_week')->nullable()->comment('id for the last workday');
            $table->dateTime('start_time')->nullable()->comment('regular starting time');
            $table->dateTime('daily_end_time')->nullable()->comment('regular end_time');
            $table->boolean('allow_registration')->comment('0=no 1=yes');
            $table->integer('default_role_for_registration')->nullable()->comment('role id for new self registered users');
            $table->string('default_email_address', 1024)->nullable()->comment('default fallback e-mail address');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of updater');
            $table->integer('archive_after')->nullable()->comment('number of days after which content is automatically archived');
            $table->integer('organisation_type')->nullable()->comment('0=school, 1=other organisation - for term set');
            $table->integer('enable_oauth')->default(0)->comment('0 = disable, 1 = enable');
            $table->text('custom_field1_name')->nullable()->comment('Name custom field 1');
            $table->text('custom_field2_name')->nullable()->comment('Name custom field 2');
            $table->integer('quorum_wild_ideas')->default(80)->comment('percentage for wild idea quorum');
            $table->integer('quorum_votes')->default(80)->comment('percentage for votes');
        });

        // au_systemlog
        Schema::create('au_systemlog', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('type')->nullable()->comment('0=standard, 1=warning, 2=error 3=nuke error');
            $table->text('message')->nullable()->comment('entry message / error message');
            $table->integer('usergroup')->nullable()->comment('group that caused the error / activity');
            $table->string('url', 2048)->nullable()->comment('url where event occurred');
            $table->dateTime('created')->nullable()->comment('creation of log entry');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update of this entry');
            $table->integer('updater_id')->nullable();
        });

        // au_texts
        Schema::create('au_texts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('creator_id')->nullable()->comment('user id of the creator');
            $table->string('headline', 1024)->nullable()->comment('headline of the text');
            $table->text('body')->nullable()->comment('the actual text');
            $table->boolean('user_needs_to_consent')->nullable()->comment('consent requirements');
            $table->integer('service_id_consent')->nullable()->comment('id of the service that the consent applies to');
            $table->string('consent_text', 512)->nullable()->comment('text displayed to user for consent');
            $table->integer('language_id')->nullable()->comment('id of the language (0=default)');
            $table->integer('location')->nullable()->comment('location (page) where the text is shown');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last_update');
            $table->integer('updater_id')->nullable()->comment('user_id of updater');
            $table->string('hash_id', 1024)->nullable()->comment('hash_id of the text');
            $table->integer('status')->nullable()->comment('0=inactive');
        });

        // au_topics
        Schema::create('au_topics', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 1024)->nullable()->comment('name of topic');
            $table->text('description_public')->nullable()->comment('public description of the topic');
            $table->text('description_internal')->nullable()->comment('description only seen by admins');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active 2=archived');
            $table->integer('order_importance')->nullable()->comment('order bias for displaying in frontend');
            $table->dateTime('created')->nullable()->comment('creation time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->string('hash_id', 1024)->nullable()->comment('hash id of the topic');
            $table->integer('updater_id')->default(0)->comment('id of the user that does the update');
            $table->integer('room_id')->default(0)->comment('id of the room the topic is in');
            $table->integer('phase_id')->default(1)->comment('Number of phase the topic is in');
            $table->integer('wild_ideas_enabled')->default(1)->comment('1=enabled 0=disabled');
            $table->dateTime('publishing_date')->nullable()->comment('Date, when the topic is active');
            $table->integer('phase_duration_0')->nullable()->comment('Duration of phase 0');
            $table->integer('phase_duration_1')->nullable()->comment('Duration of phase 1');
            $table->integer('phase_duration_2')->nullable()->comment('Duration of phase 2');
            $table->integer('phase_duration_3')->nullable()->comment('Duration of phase 3');
            $table->integer('phase_duration_4')->nullable()->comment('Duration of phase 4');
            $table->integer('type')->default(0)->comment('type of box (0=std, 1= special)');
        });

        // au_triggers
        Schema::create('au_triggers', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('trigger_id')->nullable()->comment('id of the trigger');
            $table->string('name_public', 512)->nullable()->comment('public name of the trigger');
            $table->string('name_internal', 512)->nullable()->comment('internal name of the trigger');
            $table->text('description_public')->nullable()->comment('description of the trigger');
            $table->text('description_internal')->nullable()->comment('internal description');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active 2=suspended');
            $table->dateTime('created')->nullable()->comment('create time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of the last updater');
        });

        // au_user_levels
        Schema::create('au_user_levels', function (Blueprint $table) {
            $table->integer('level')->primary()->comment('id of level');
            $table->string('name', 1024)->nullable()->comment('name of level');
            $table->text('description')->nullable()->comment('description of userlevel / rights');
            $table->integer('status')->nullable()->comment('0=inactive 1=active');
        });

        // au_users_basedata
        Schema::create('au_users_basedata', function (Blueprint $table) {
            $table->increments('id');
            $table->string('realname', 2048)->nullable()->comment('real name of the user');
            $table->string('displayname', 1024)->nullable()->comment('name displayed in frontend');
            $table->string('username', 512)->nullable()->comment('username of user should be email address');
            $table->string('email', 2048)->nullable()->comment('email address');
            $table->string('pw', 2048)->nullable()->comment('pw');
            $table->string('position', 1024)->nullable()->comment('position within the organisation - not mandatory');
            $table->string('hash_id', 1024)->nullable()->comment('hashed id userspecific');
            $table->text('about_me')->nullable()->comment('about me text');
            $table->integer('registration_status')->nullable()->comment('Registration status 0=new, 1=in registration 2=completed');
            $table->integer('status')->nullable()->comment('0=inactive 1=active 2=suspended 3=archive');
            $table->dateTime('created')->nullable()->comment('created time');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->dateTime('last_update_retrieval')->nullable()->comment('last update_retrieval');
            $table->integer('updater_id')->nullable()->comment('user_id of the updater');
            $table->integer('creator_id')->nullable()->comment('user_id of the creator');
            $table->string('bi', 1024)->nullable()->comment('blind index');
            $table->integer('userlevel')->nullable()->comment('level of the user (access rights)');
            $table->integer('infinite_votes')->nullable()->comment('0=inactive 1= active (this user has infinite votes)');
            $table->dateTime('last_login')->nullable()->comment('date of last login');
            $table->integer('presence')->default(1)->comment('0 = user is absent, 1= user is present');
            $table->dateTime('absent_until')->nullable()->comment('date until the user is absent');
            $table->integer('auto_delegation')->default(0)->comment('1=on, 0=off');
            $table->integer('trustee_id')->nullable()->comment('id of the trusted user');
            $table->integer('o1')->nullable();
            $table->integer('o2')->nullable();
            $table->integer('o3')->nullable();
            $table->integer('consents_given')->default(0)->comment('consents given');
            $table->integer('consents_needed')->default(0)->comment('needed consents');
            $table->string('temp_pw', 256)->nullable()->comment('temp pw for user');
            $table->integer('pw_changed')->default(0)->comment('user has changed his initial pw');
            $table->boolean('refresh_token')->default(false)->comment('refresh token request');
            $table->json('roles')->default('[]')->comment('roles of the user');
        });

        // au_users_settings
        Schema::create('au_users_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable()->comment('id of the user');
            $table->integer('external_service_login')->nullable()->comment('SSO / OAuth2 login 0=no 1=yes');
            $table->dateTime('created')->nullable();
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update');
            $table->integer('updater_id')->nullable()->comment('user id of the updater');
            $table->integer('external_service_id')->nullable()->comment('id of the used service for authentication');
        });

        // au_votes
        Schema::create('au_votes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->nullable()->comment('id of voting user');
            $table->integer('idea_id')->nullable()->comment('id of idea');
            $table->integer('vote_value')->nullable()->comment('value of the vote (-1, 0, +1)');
            $table->integer('status')->nullable()->comment('0=inactive, 1=active, 2= temporarily suspended');
            $table->dateTime('created')->nullable()->comment('time of first creation');
            $table->dateTime('last_update')->nullable()->useCurrentOnUpdate()->comment('last update on this dataset');
            $table->string('hash_id', 1024)->nullable()->comment('hash id of the vote');
            $table->integer('vote_weight')->nullable()->comment('absolute value for given votes');
            $table->integer('number_of_delegations')->nullable()->comment('number of delegated votes included');
            $table->string('comment', 2048)->nullable()->comment('Comment that the user added to a vote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('au_votes');
        Schema::dropIfExists('au_users_settings');
        Schema::dropIfExists('au_users_basedata');
        Schema::dropIfExists('au_user_levels');
        Schema::dropIfExists('au_triggers');
        Schema::dropIfExists('au_topics');
        Schema::dropIfExists('au_texts');
        Schema::dropIfExists('au_systemlog');
        Schema::dropIfExists('au_system_global_config');
        Schema::dropIfExists('au_system_current_state');
        Schema::dropIfExists('au_services');
        Schema::dropIfExists('au_rooms');
        Schema::dropIfExists('au_roles');
        Schema::dropIfExists('au_reported');
        Schema::dropIfExists('au_rel_users_triggers');
        Schema::dropIfExists('au_rel_users_media');
        Schema::dropIfExists('au_rel_user_user');
        Schema::dropIfExists('au_rel_topics_media');
        Schema::dropIfExists('au_rel_topics_ideas');
        Schema::dropIfExists('au_rel_rooms_users');
        Schema::dropIfExists('au_rel_rooms_media');
        Schema::dropIfExists('au_rel_ideas_media');
        Schema::dropIfExists('au_rel_ideas_comments');
        Schema::dropIfExists('au_rel_groups_users');
        Schema::dropIfExists('au_rel_groups_media');
        Schema::dropIfExists('au_rel_categories_rooms');
        Schema::dropIfExists('au_rel_categories_media');
        Schema::dropIfExists('au_rel_categories_ideas');
        Schema::dropIfExists('au_phases_global_config');
        Schema::dropIfExists('au_messages');
        Schema::dropIfExists('au_media');
        Schema::dropIfExists('au_likes');
        Schema::dropIfExists('au_ideas');
        Schema::dropIfExists('au_groups');
        Schema::dropIfExists('au_delegation');
        Schema::dropIfExists('au_consent');
        Schema::dropIfExists('au_comments');
        Schema::dropIfExists('au_commands');
        Schema::dropIfExists('au_change_password');
        Schema::dropIfExists('au_categories');
        Schema::dropIfExists('au_activitylog');
    }
};
