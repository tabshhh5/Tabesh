<?php
/**
 * AI Assistant Interface
 *
 * Defines the contract for AI assistants with role-based capabilities
 * Assistants are specialized AI entities with specific purposes and access levels
 *
 * @package Tabesh
 * @subpackage AI
 * @since 1.1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Tabesh_AI_Assistant_Interface
 *
 * Contract for all AI assistants
 */
interface Tabesh_AI_Assistant_Interface {

	/**
	 * Get the unique identifier for this assistant
	 *
	 * @return string Assistant identifier
	 */
	public function get_assistant_id();

	/**
	 * Get the human-readable name of the assistant
	 *
	 * @return string Assistant name
	 */
	public function get_assistant_name();

	/**
	 * Get the description of what this assistant does
	 *
	 * @return string Assistant description
	 */
	public function get_assistant_description();

	/**
	 * Get the allowed roles for this assistant
	 *
	 * @return array Array of WordPress role names
	 */
	public function get_allowed_roles();

	/**
	 * Get the capabilities this assistant has
	 *
	 * @return array Array of capability identifiers
	 */
	public function get_capabilities();

	/**
	 * Check if the current user can use this assistant
	 *
	 * @param int $user_id Optional user ID, defaults to current user
	 * @return bool True if user can use this assistant
	 */
	public function can_user_access( $user_id = 0 );

	/**
	 * Process a request to this assistant
	 *
	 * @param string $request The user's request
	 * @param array  $context Additional context data
	 * @return array Response array with 'success', 'message', and optional 'data' keys
	 */
	public function process_request( $request, $context = array() );

	/**
	 * Get the system prompt for this assistant
	 *
	 * This defines the assistant's personality and instructions
	 *
	 * @return string System prompt
	 */
	public function get_system_prompt();
}
