<?php
/**
 * Name: Image Scores (Text)
 * Author: Shish <webmaster@shishnet.org>
 * License: GPLv2
 * Description: Allow users to score images
 */

class TextScoreSetEvent extends Event {
	var $image_id, $user, $score;

	public function TextScoreSetEvent($image_id, $user, $score) {
		$this->image_id = $image_id;
		$this->user = $user;
		$this->score = $score;
	}
}

class TextScore extends Extension {
	var $theme;

	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("text_score", "TextScoreTheme");

		if(is_a($event, 'InitExtEvent')) {
			global $config;
			if($config->get_int("ext_text_score_version", 0) < 1) {
				$this->install();
			}
			$config->set_default_bool("text_score_anon", true);
		}
		
		if(is_a($event, 'ImageInfoBoxBuildingEvent')) {
			global $user;
			global $config;
			if(!$user->is_anonymous() || $config->get_bool("text_score_anon")) {
				$event->add_part($this->theme->get_scorer_html($event->image));
			}
		}
		
		if(is_a($event, 'ImageInfoSetEvent')) {
			global $user;
			$i_score = int_escape($_POST['text_score__score']);
			
			if($i_score >= -2 || $i_score <= 2) {
				send_event(new TextScoreSetEvent($event->image_id, $user, $i_score));
			}
		}

		if(is_a($event, 'TextScoreSetEvent')) {
			if(!$event->user->is_anonymous() || $config->get_bool("text_score_anon")) {
				$this->add_vote($event->image_id, $event->user->id, $event->score);
			}
		}

		if(is_a($event, 'ImageDeletionEvent')) {
			global $database;
			$database->execute("DELETE FROM text_score_votes WHERE image_id=?", array($event->image->id));
		}
		
		if(is_a($event, 'SetupBuildingEvent')) {
			$sb = new SetupBlock("Text Score");
			$sb->add_bool_option("text_score_anon", "Allow anonymous votes: ");
			$event->panel->add_block($sb);
		}

		if(is_a($event, 'ParseLinkTemplateEvent')) {
			$event->replace('$text_score', $this->theme->score_to_name($event->image->text_score));
		}
	}

	private function install() {
		global $database;
		global $config;

		if($config->get_int("ext_text_score_version") < 1) {
			$database->Execute("ALTER TABLE images ADD COLUMN text_score INTEGER NOT NULL DEFAULT 0");
			$database->Execute("CREATE INDEX images__text_score ON images(text_score)");
			$database->Execute("
				CREATE TABLE text_score_votes (
					image_id INTEGER NOT NULL,
					user_id INTEGER NOT NULL,
					score INTEGER NOT NULL,
					UNIQUE(image_id, user_id),
					INDEX(image_id)
				)
			");
			$config->set_int("ext_text_score_version", 1);
		}
	}

	private function add_vote($image_id, $user_id, $score) {
		global $database;
		// TODO: update if already voted
		$database->Execute(
			"REPLACE INTO text_score_votes(image_id, user_id, score) VALUES(?, ?, ?)",
			array($image_id, $user_id, $score));
		$database->Execute(
			"UPDATE images SET text_score=(SELECT AVG(score) FROM text_score_votes WHERE image_id=?) WHERE id=?",
			array($image_id, $image_id));
	}
}
add_event_listener(new TextScore());
?>
