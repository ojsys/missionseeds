<?php
/**
 * Registry of every settings key that may be edited inline (contenteditable)
 * directly on the public site.
 *
 * This replaces the flat INLINE_EDITABLE_KEYS list from the single-page build.
 * Grouping by page keeps it readable as the site grows, and gives the admin
 * "page copy" screens something to iterate over.
 *
 * SECURITY: admin/api.php will only write a key that appears here, so this
 * list is the allow-list for inline editing. Structured data (dates, URLs,
 * phone numbers, anything parsed rather than printed) must NOT be added —
 * it belongs in admin/settings.php where it can be validated.
 */

const CONTENT_KEYS = [

    'global' => [
        'footer_tagline'   => 'Footer tagline',
        'footer_address'   => 'Footer address line',
    ],

    'home' => [
        'home_eyebrow'      => 'Hero eyebrow',
        'home_heading'      => 'Hero heading',
        'home_subheading'   => 'Hero paragraph',
        'home_pilot_eyebrow'=> 'Pilot card eyebrow',
        'home_pilot_heading'=> 'Pilot card heading',
        'home_pilot_body'   => 'Pilot card paragraph',
        'home_stats_heading'=> 'Impact strip heading',
        'home_stats_intro'  => 'Impact strip intro',
        'home_pathway_heading' => 'Pathway teaser heading',
        'home_pathway_intro'   => 'Pathway teaser intro',
        'home_stories_heading' => 'Stories teaser heading',
        'home_stories_intro'   => 'Stories teaser intro',
        'home_cta_heading'  => 'Closing CTA heading',
        'home_cta_body'     => 'Closing CTA paragraph',
    ],

    'about' => [
        'about_eyebrow'          => 'Page eyebrow',
        'about_heading'          => 'Page heading',
        'about_intro'            => 'Page intro',
        'about_vision_heading'   => 'Vision heading',
        'about_vision_body'      => 'Vision body',
        'about_mission_heading'  => 'Mission heading',
        'about_mission_body'     => 'Mission body',
        'about_why_heading'      => 'Why we exist heading',
        'about_why_body'         => 'Why we exist body',
        'about_model_heading'    => 'Church-based model heading',
        'about_model_body'       => 'Church-based model body',
        'about_framework_heading'=> 'Sustainability framework heading',
        'about_framework_body'   => 'Sustainability framework body',
        'about_future_heading'   => 'Future expansion heading',
        'about_future_body'      => 'Future expansion body',
    ],

    'pathway' => [
        'pathway_eyebrow' => 'Page eyebrow',
        'pathway_heading' => 'Page heading',
        'pathway_intro'   => 'Page intro',
        'pathway_outro'   => 'Closing note',
    ],

    'tracker' => [
        'tracker_eyebrow'          => 'Page eyebrow',
        'tracker_heading'          => 'Page heading',
        'tracker_intro'            => 'Page intro',
        'tracker_milestones_heading' => 'Milestone timeline heading',
        'tracker_privacy_note'     => 'Privacy note',
    ],

    'cooperative' => [
        'coop_eyebrow'        => 'Page eyebrow',
        'coop_heading'        => 'Page heading',
        'coop_intro'          => 'Page intro',
        'coop_savings_heading'=> 'Savings section heading',
        'coop_savings_body'   => 'Savings section body',
        'coop_governance_heading' => 'Governance heading',
        'coop_governance_body'    => 'Governance body',
        'coop_reporting_heading'  => 'Reporting heading',
        'coop_reporting_body'     => 'Reporting body',
        'coop_maturity_heading'   => 'Fund maturity heading',
        'coop_maturity_body'      => 'Fund maturity body',
    ],

    'churches' => [
        'churches_eyebrow' => 'Page eyebrow',
        'churches_heading' => 'Page heading',
        'churches_intro'   => 'Page intro',
        'churches_privacy_note' => 'Privacy note',
    ],

    'stories' => [
        'stories_eyebrow' => 'Page eyebrow',
        'stories_heading' => 'Page heading',
        'stories_intro'   => 'Page intro',
    ],

    'resources' => [
        'resources_eyebrow'     => 'Page eyebrow',
        'resources_heading'     => 'Page heading',
        'resources_intro'       => 'Page intro',
        'resources_staff_note'  => 'Restricted section note',
    ],

    'contact' => [
        'contact_eyebrow'      => 'Page eyebrow',
        'contact_heading'      => 'Page heading',
        'contact_intro'        => 'Page intro',
        'contact_eoi_heading'  => 'Expression of interest heading',
        'contact_eoi_body'     => 'Expression of interest body',
        'contact_partner_heading' => 'Partnership heading',
        'contact_partner_body'    => 'Partnership body',
    ],

    // The original call-for-applications landing page. These keys are the ones
    // that shipped in install.sql — do not rename them, the live site uses them.
    'call' => [
        'hero_eyebrow'       => 'Hero eyebrow',
        'hero_heading'       => 'Hero heading',
        'hero_subheading'    => 'Hero paragraph',
        'seed_eyebrow'       => 'Intro eyebrow',
        'seed_heading'       => 'Intro heading',
        'seed_body'          => 'Intro body',
        'who_eyebrow'        => 'Criteria eyebrow',
        'who_heading'        => 'Criteria heading',
        'who_intro'          => 'Criteria intro',
        'pilot_eyebrow'      => 'Spotlight eyebrow',
        'pilot_heading'      => 'Spotlight heading',
        'pilot_body'         => 'Spotlight body',
        'receive_eyebrow'    => 'Benefits eyebrow',
        'receive_heading'    => 'Benefits heading',
        'partners_eyebrow'   => 'Partners eyebrow',
        'partners_heading'   => 'Partners heading',
        'partners_intro'     => 'Partners intro',
        'harvest_eyebrow'    => 'Closing eyebrow',
        'harvest_heading'    => 'Closing heading',
        'harvest_subheading' => 'Closing paragraph',
        'deadline_note'      => 'Deadline note',
        'eoi_button_label'   => 'Apply button label',
        'call_closed_note'   => 'Applications-closed note',
    ],
];

/** Flat list of every inline-editable key. */
function inline_editable_keys(): array {
    static $flat = null;
    if ($flat === null) {
        $flat = [];
        foreach (CONTENT_KEYS as $keys) {
            foreach ($keys as $key => $label) {
                $flat[] = $key;
            }
        }
    }
    return $flat;
}

/** The allow-list check used by admin/api.php before writing a setting. */
function is_inline_editable(string $key): bool {
    return in_array($key, inline_editable_keys(), true);
}
