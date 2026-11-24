<?php

namespace App\Support;

/**
 * Nigeria's 36 states + the FCT — the only accepted values for a new checkout shipping address'
 * state (mirrored for the dropdown in resources/js/utils/nigerianStates.js).
 */
class NigerianStates
{
    public const ALL = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
        'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT (Abuja)', 'Gombe',
        'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
        'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto',
        'Taraba', 'Yobe', 'Zamfara',
    ];
}
