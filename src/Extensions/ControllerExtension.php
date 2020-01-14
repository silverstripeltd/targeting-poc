<?php
namespace Silverstripe\TargetingPoc\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;

class ControllerExtension extends DataExtension
{
    public function onAfterInit()
    {
        /** @var HTTPRequest $request */
        $request = $this->getOwner()->getRequest();

        /** @var Session $session */
        $session = null;
        try {
            $session = $request->getSession();
        } catch (\BadMethodCallException $e) {
            // Nested block controllers do not inherit HTTP request with sessions correctly
            return false;
        }

        // Authors can simulate different targeting criteria
        if (Permission::check('CMS_ACCESS_CMSMain')) {
            // TODO Groups support

            $useSegments = Config::inst()->get('Silverstripe\TargetingPoc\Config', 'use_segments');
            $useCountry = Config::inst()->get('Silverstripe\TargetingPoc\Config', 'use_country');

            // Store segments
            // Resolves _segments[foo]=1&_segments[bar]=0, and stores as [foo] (unsets bar)
            if ($useSegments && $request && $segments = (array)$request->getVar('_segments')) {
                // TODO Filter valid values
                // Auto-starts session, and can store arrays natively.
                $session->set('segments', json_encode($segments));
            }

            // Store country
            if ($useCountry && $request && $country = $request->getVar('_country')) {
                // TODO Filter valid values
                $session->set('country', $country);
            }

            Requirements::css('silverstripe/targeting-poc:client/css/betternavigator.css');
        }
    }

    public function getBetterButtonsTargetingForm()
    {
        /** @var HTTPRequest $request */
        $request = $this->getOwner()->getRequest();
        $session = $request->getSession();

        $segments = Config::inst()->get(BaseElement::class, 'segments');
        $countries = Config::inst()->get(BaseElement::class, 'countries');

        // Comes through BaseElementExtension, but since the extension is applied to the controller,
        // the config property is available there
        $currentSegments = $session->get('segments') ? json_decode($session->get('segments'), true) : null;
        $currentCountry = $session->get('country') ? $session->get('country') : null;

        $useSegments = Config::inst()->get('Silverstripe\TargetingPoc\Config', 'use_segments');
        $useCountry = Config::inst()->get('Silverstripe\TargetingPoc\Config', 'use_country');

        $fields = FieldList::create();
        if ($useSegments) {
            $fields->push(
                CheckboxSetField::create('_segments', 'Segments', $segments)
                    ->setValue($currentSegments)
            );
        }

        if ($useCountry) {
            $fields->push(
                DropdownField::create('_country', 'Country', $countries)
                    ->setValue($currentCountry)
            );
        }

        $actions = FieldList::create([
            FormAction::create('Apply', 'Apply')
        ]);

        $form = Form::create(
            $this->getOwner(),
            'BetterButtonsTargetingForm',
            $fields,
            $actions
        );

        return $form
            ->disableSecurityToken()
            ->setFormMethod('GET')
            ->addExtraClass('targeting')
            // Submit to same URL which applies GET params to session
            ->setFormAction($request->getURL());
    }


}