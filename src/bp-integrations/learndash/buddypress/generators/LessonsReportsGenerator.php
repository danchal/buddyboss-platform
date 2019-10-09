<?php
/**
 * BuddyBoss LearnDash integration quizzes reports generator.
 *
 * @package BuddyBoss\LearnDash
 * @since BuddyBoss 1.0.0
 */

namespace Buddyboss\LearndashIntegration\Buddypress\Generators;

use Buddyboss\LearndashIntegration\Library\ReportsGenerator;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Extends report generator for lessons reports
 *
 * @since BuddyBoss 1.0.0
 */
class LessonsReportsGenerator extends ReportsGenerator {

	/**
	 * Constructor
	 *
	 * @since BuddyBoss 1.0.0
	 */
	public function __construct() {
		 $this->completed_table_title  = __( 'Completed Lessons', 'buddyboss' );
		$this->incompleted_table_title = __( 'Incomplete Lessons', 'buddyboss' );

		parent::__construct();
	}

	/**
	 * Returns the columns and their settings
	 *
	 * @since BuddyBoss 1.0.0
	 */
	protected function columns() {
		if ( groups_is_user_mod( bp_loggedin_user_id(), groups_get_current_group()->id ) || groups_is_user_admin( bp_loggedin_user_id(), groups_get_current_group()->id ) || bp_current_user_can( 'bp_moderate' ) ) {
			return [
				'user_id'         => $this->column( 'user_id' ),
				'user'            => $this->column( 'user' ),
				'course_id'       => $this->column( 'course_id' ),
				'course'          => $this->column( 'course' ),
				'lesson'          => [
					'label'     => __( 'Lesson', 'buddyboss' ),
					'sortable'  => true,
					'order_key' => 'post_title',
				],
				'lesson_points'   => [
					'label'     => __( 'Points Earned', 'buddyboss' ),
					'sortable'  => false,
					'order_key' => 'activity_points',
				],
				'start_date'      => $this->column( 'start_date' ),
				'completion_date' => $this->column( 'completion_date' ),
				'updated_date'    => $this->column( 'updated_date' ),
				'time_spent'      => $this->column( 'time_spent' ),
			];
		} else {
			return [
				'user_id'         => $this->column( 'user_id' ),
				//'user'            => $this->column( 'user' ),
				'course_id'       => $this->column( 'course_id' ),
				'course'          => $this->column( 'course' ),
				'lesson'          => [
					'label'     => __( 'Lesson', 'buddyboss' ),
					'sortable'  => true,
					'order_key' => 'post_title',
				],
				'lesson_points'   => [
					'label'     => __( 'Points Earned', 'buddyboss' ),
					'sortable'  => false,
					'order_key' => 'activity_points',
				],
				'start_date'      => $this->column( 'start_date' ),
				'completion_date' => $this->column( 'completion_date' ),
				'updated_date'    => $this->column( 'updated_date' ),
				'time_spent'      => $this->column( 'time_spent' ),
			];
		}
	}

	/**
	 * Format the activity results for each column
	 *
	 * @since BuddyBoss 1.0.0
	 */
	protected function formatData( $activity ) {
		if ( groups_is_user_mod( bp_loggedin_user_id(), groups_get_current_group()->id ) || groups_is_user_admin( bp_loggedin_user_id(), groups_get_current_group()->id ) || bp_current_user_can( 'bp_moderate' ) ) {
			return array(
				'user_id'         => $activity->user_id,
				'user'            => bp_core_get_user_displayname( $activity->user_id ),
				'course_id'       => $activity->activity_course_id,
				'course'          => $activity->activity_course_title,
				'lesson'          => $activity->post_title,
				'start_date'      => date_i18n( bp_get_option( 'date_format' ),
					strtotime( $activity->activity_started_formatted ) ),
				'lesson_points'   => ReportsGenerator::coursePointsEarned( $activity ),
				'completion_date' => $this->completionDate( $activity ),
				'updated_date'    => $this->updatedDate( $activity ),
				'time_spent'      => $this->timeSpent( $activity ),
			);
		} else {
			return array(
				'user_id'         => $activity->user_id,
				//'user'            => bp_core_get_user_displayname( $activity->user_id ),
				'course_id'       => $activity->activity_course_id,
				'course'          => $activity->activity_course_title,
				'lesson'          => $activity->post_title,
				'start_date'      => date_i18n( bp_get_option( 'date_format' ),
					strtotime( $activity->activity_started_formatted ) ),
				'lesson_points'   => ReportsGenerator::coursePointsEarned( $activity ),
				'completion_date' => $this->completionDate( $activity ),
				'updated_date'    => $this->updatedDate( $activity ),
				'time_spent'      => $this->timeSpent( $activity ),
			);
		}
	}

	/**
	 * Overwrite results value for export
	 *
	 * @since BuddyBoss 1.0.0
	 */
	protected function formatDataForExport( $data, $activity ) {
		$data['status'] = empty( $activity->activity_completed ) ? $this->incompleted_table_title : $this->completed_table_title;

		return $data;
	}

	/**
	 * Overwrite results value for display
	 *
	 * @since BuddyBoss 1.0.0
	 */
	protected function formatDataForDisplay( $data, $activity ) {
		$circle = '';
		if ( $activity->activity_status == '1') {
			$circle = '<div class="i-progress i-progress-completed"><i class="bb-icon-check"></i></div>';
		} else {
			$circle = '<div class="i-progress i-progress-not-completed"><i class="bb-icon-circle"></i></div>';
		}
		$data = wp_parse_args(
			array(
				'lesson' => sprintf(
					$circle . ' <a href="%s" target="_blank">%s</a>',
					get_permalink( $activity->post_id ),
					$activity->post_title
				),
			),
			$data
		);

		return parent::formatDataForDisplay( $data, $activity );
	}
}
