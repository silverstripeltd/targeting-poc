<?php

namespace Silverstripe\TargetingPoc\Extensions;


use DNADesign\Elemental\Models\BaseElement;
use GeoIp2\Exception\AddressNotFoundException;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
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

            case "Segments":
                // TODO Use ListboxField->getValue()
                $segments = $element->Segments ? json_decode($element->Segments, true) : null;
                return ($segments && $this->visitorInSegments($segments));
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
        // Default to session based override
        $request = Injector::inst()->get(HTTPRequest::class);
        $session = $request->getSession();

        if ($country = $session->get('country')) {
            return ($isoCode == $country);
        }

        // Try geotargeting as fallback
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

    public function visitorInSegments($requiredSegments = [])
    {
        // This should use a third party source of segmentation,
        // for demo purposes we're mocking it up throuh session data.
        // Not using getOwner()->getRequest() here because
        // the controller might be an improperly nested one (ElementController nested in PageController),
        // and doesn't carry over the originally created request/session in the parent controller
        $request = Injector::inst()->get(HTTPRequest::class);
        $session = $request->getSession();

        $visitorSegments = $session->get('segments') ? json_decode($session->get('segments'), true) : null;

        // Show when *all* segments match
        return ($visitorSegments && !array_diff($requiredSegments, $visitorSegments));

    }
}