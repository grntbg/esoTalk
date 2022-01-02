<?php

// esoTalk Upgrade Script
// 1.0.0b2 to 1.0.0g2

//------ BEGIN CONFIG

$db_host = "localhost";
$db_user = "toby";
$db_pass = "";

$old_database = "6rupees_old";
$old_prefix = "et_";

$new_database = "6rupees";
$new_prefix = "et_";

$join_time = time() - 60 * 60 * 24 * 365 * 2; // 2 years ago

//------ END CONFIG

echo "<pre>";

require "formatter.php";

function fail($message)
{
	echo "FAIL!\n\n$message";
	exit;
}

function ok()
{
	echo "OK!\n";
}

function query($query)
{
	$result = mysql_query($query);
	if (!$result) fail(mysql_error()."\n$query");
	return $result;
}

// Connect to the database.
echo "Connecting to the database... ";
mysql_connect($db_host, $db_user, $db_pass) or fail(mysql_error());
mysql_select_db($new_database) or fail(mysql_error());
ok();

// Delete all data from the new database.
echo "Clearing data from new database... ";
query("DELETE FROM {$new_prefix}activity");
query("DELETE FROM {$new_prefix}conversation");
query("DELETE FROM {$new_prefix}member");
query("DELETE FROM {$new_prefix}member_conversation");
query("DELETE FROM {$new_prefix}member_group");
query("DELETE FROM {$new_prefix}post");
ok();

// Copy over conversations.
echo "Copying conversations... ";
query("INSERT INTO {$new_prefix}conversation
	(conversationId, title, channelId, private, sticky, locked, countPosts, startMemberId, startTime, lastPostMemberId, lastPostTime)
	(SELECT conversationId, title, 1, private, sticky, locked, posts, startMember, startTime, lastPostMember, lastPostTime FROM {$old_database}.{$old_prefix}conversations)");
ok();

// Convert conversation formatting.
echo "Converting conversation formatting... ";
$result = query("SELECT conversationId, title FROM {$new_prefix}conversation");
while ($conversation = mysql_fetch_assoc($result)) {
	query("UPDATE {$new_prefix}conversation SET title='".mysql_real_escape_string(desanitize($conversation["title"]))."' WHERE conversationId={$conversation["conversationId"]}");
}
ok();

// Copy over posts.
echo "Copying posts... ";
query("INSERT INTO {$new_prefix}post
	(postId, conversationId, memberId, time, editMemberId, editTime, deleteMemberId, deleteTime, title, content)
	(SELECT postId, conversationId, memberId, time, editMember, editTime, deleteMember, IF(deleteMember IS NOT NULL, editTime, NULL), title, content FROM {$old_database}.{$old_prefix}posts)");
ok();

// Convert post formatting.
echo "Converting post formatting... ";
$formatter = new Formatter;
$result = query("SELECT postId, title, content FROM {$new_prefix}post");
while ($post = mysql_fetch_assoc($result)) {
	$content = desanitize($formatter->revert($post["content"]));
	query("UPDATE {$new_prefix}post SET title='".mysql_real_escape_string(desanitize($post["title"]))."', content='".mysql_real_escape_string($content)."' WHERE postId={$post["postId"]}");
}
ok();

// Copy over members.
echo "Copying members... ";
query("INSERT INTO {$new_prefix}member
	(memberId, username, email, account, confirmedEmail, password, joinTime, lastActionTime, avatarFormat, countPosts, countConversations)
	(SELECT memberId, name, email, IF(account='Administrator', 'administrator', IF(account='Suspended', 'suspended', 'member')), IF(account='Unvalidated',0,1), 'asdf', $join_time, lastSeen, avatarFormat, 
		(SELECT COUNT(postId) FROM {$new_prefix}post p WHERE p.memberId=m.memberId),
		(SELECT COUNT(conversationId) FROM {$new_prefix}conversation c WHERE c.startMemberId=m.memberId)
		FROM {$old_database}.{$old_prefix}members m)");
ok();

// Create member group associations.
echo "Creating member-group associations... ";
query("INSERT INTO {$new_prefix}member_group
	(memberId, groupId)
	(SELECT memberId, 1 FROM {$old_database}.{$old_prefix}members WHERE account='Moderator')");
ok();

// Copy over member-conversation status.
echo "Copying member-conversation associations... ";
query("INSERT INTO {$new_prefix}member_conversation
	(conversationId, type, id, allowed, starred, lastRead, draft)
	(SELECT conversationId,
		IF(memberId IN ('Administrator','Moderator','Member'), 'group', 'member'),
		CASE memberId
			WHEN 'Administrator' THEN -3
			WHEN 'Moderator' THEN 1
			WHEN 'Member' THEN -2
			ELSE memberId
		END,
		allowed, starred, lastRead, draft
		FROM {$old_database}.{$old_prefix}status)");
ok();

// Update channel conversation/post counts.
echo "Updating channel conversation/post counts... ";
query("UPDATE {$new_prefix}channel ch SET
	countConversations=(SELECT COUNT(conversationId) FROM {$new_prefix}conversation c WHERE c.channelId=ch.channelId),
	countPosts=(SELECT SUM(countPosts) FROM {$new_prefix}conversation c WHERE c.channelId=ch.channelId)");
ok();

echo "DONE!";