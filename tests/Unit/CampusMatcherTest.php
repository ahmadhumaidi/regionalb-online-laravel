<?php

namespace Tests\Unit;

use App\Support\CampusMatcher;
use Tests\TestCase;

class CampusMatcherTest extends TestCase
{
    public function test_matches_exact_label(): void
    {
        $this->assertTrue(CampusMatcher::matches('STIESIA Surabaya', 'STIESIA Surabaya'));
    }

    public function test_matches_case_insensitively(): void
    {
        $this->assertTrue(CampusMatcher::matches('stiesia surabaya', 'STIESIA SURABAYA'));
    }

    public function test_matches_full_name_against_parenthetical_abbreviation(): void
    {
        $this->assertTrue(CampusMatcher::matches('Universitas Patria Artha ( UPA )', 'Universitas Patria Artha'));
        $this->assertTrue(CampusMatcher::matches('UUI', 'Universitas Ubudiyah Indonesia (UUI)'));
    }

    public function test_matches_known_alias_pair(): void
    {
        $this->assertTrue(CampusMatcher::matches(
            'Sekolah Tinggi Bahasa Asing LIA Yogyakarta (STBA LIA)',
            'STBA Lia Yogyakarta'
        ));
    }

    public function test_matches_either_side_of_a_merged_alias(): void
    {
        $this->assertTrue(CampusMatcher::matches('IKIP Widya Darma', 'STIE Widya Darma'));
    }

    public function test_does_not_match_unrelated_campuses(): void
    {
        $this->assertFalse(CampusMatcher::matches('STIESIA Surabaya', 'Universitas Patria Artha'));
    }

    public function test_does_not_match_blank_labels(): void
    {
        $this->assertFalse(CampusMatcher::matches('', 'STIESIA Surabaya'));
        $this->assertFalse(CampusMatcher::matches('STIESIA Surabaya', ''));
    }
}
