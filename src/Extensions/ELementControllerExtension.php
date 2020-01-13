<?php

namespace Silverstripe\TargetingPoc\Extensions;


use DNADesign\Elemental\Models\BaseElement;
use GeoIp2\Exception\AddressNotFoundException;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Security\Security;
use GeoIp2\Database\Reader;

class ElementControllerExtension extends Extension
{
    public function IsElementVisible()
    {
        /** @var BaseElement $element */
        $element = $this->owner->getElement();
        $member = Security::getCurrentUser();

        switch($element->ShowTo) {
            case "LoggedOut":
                return !($member && $member->exists());
            break;

            case "LoggedIn":
                return ($member && $member->exists());
            break;

            case "Group":
                return ($member && $member->exists() && $element->ShowToGroupID > 0 && $member->inGroup($element->ShowToGroup()));
            break;

            case "ByCountry":
                return strlen($element->Country) > 0 && $this->visitorInCountry($element->Country);

            case "Everyone":
            default:
                return true;
        }
    }

    public function visitorInCountry($isoCode)
    {
        $path = ModuleLoader::getModule('silverstripe/targeting-poc')
            ->getResource('data/GeoLite2-Country.mmdb')
            ->getAbsolutePath();

        $reader = new Reader($path);

        try {
            $record = $reader->country($_SERVER['REMOTE_ADDR']);
        } catch(AddressNotFoundException $e) {
            return $isoCode == 'NZ';
        }

        return $record->country->isoCode == $isoCode;
    }
}