<?php

/**
 * Meta Generator Service
 *
 * Business logic for meta title and description generation.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Services
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Services;

if (!defined('ABSPATH')) {
	exit;
}

class Meta_Generator
{

	/**
	 * Generate meta title and description
	 *
	 * @param string $keyword Target keyword
	 * @param string $business_name Business name
	 * @param string $description Page description
	 * @param string $page_type Page type
	 * @return array Generation result
	 * @since 1.0.0
	 */
	public function generate(
		string $keyword,
		string $business_name,
		string $description,
		string $page_type = 'service'
	): array {
		$gemini_api = new Gemini_API();

		$result = $gemini_api->generate_meta(
			$keyword,
			$business_name,
			$description,
			$page_type
		);

		if (!$result['success']) {
			return $result;
		}

		// Validate length constraints
		$title_length = strlen($result['title']);
		$desc_length = strlen($result['description']);

		// If lengths are way off, regenerate or provide fallback
		if ($title_length > 70 || $desc_length > 170) {
			// Truncate if too long
			if ($title_length > 60) {
				$result['title'] = substr($result['title'], 0, 57) . '...';
				$title_length = 60;
			}

			if ($desc_length > 160) {
				$result['description'] = substr($result['description'], 0, 157) . '...';
				$desc_length = 160;
			}
		}

		return [
			'success' => true,
			'title' => $result['title'],
			'description' => $result['description'],
			'title_length' => $title_length,
			'description_length' => $desc_length,
			'tokens_used' => $result['tokens_used'] ?? 0,
			'tokens_breakdown' => $result['tokens_breakdown'] ?? null
		];
	}

	/**
	 * Estimate tokens used (approximate)
	 *
	 * @param string $text Text content
	 * @return int Estimated token count
	 * @since 1.0.0
	 */
	private function estimate_tokens(string $text): int
	{
		// Rough estimate: 1 token ≈ 4 characters
		return (int) ceil(strlen($text) / 4);
	}
}
