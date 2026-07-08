<?php
/**
 * Renderer tests.
 *
 * @package PlaqueIt
 */

use PHPUnit\Framework\TestCase;

/** Renderer test case. */
class RendererTest extends TestCase {

	public function test_mm_to_px_uses_76_dpi(): void {
		$this->assertEqualsWithDelta( 76.0, Plaque_It_Renderer::mm_to_px( 25.4 ), 0.0001 );
	}
}
