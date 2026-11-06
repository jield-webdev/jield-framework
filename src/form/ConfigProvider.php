<?php

declare(strict_types=1);

namespace Jield\Form;

use Jield\Form\View\Helper\Factory\FormElementErrorsFactory;
use Jield\Form\View\Helper\Factory\FormElementFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;

/** @psalm-import-type ServiceManagerConfiguration from ServiceManager */
final class ConfigProvider
{
    /**
     * Return general-purpose jield-i18n configuration.
     *
     * @return array{
     *     dependencies: ServiceManagerConfiguration,
     *     view_helpers: ServiceManagerConfiguration,
     *     ...
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'view_helpers' => $this->getViewHelperConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration.
     *
     * @return ServiceManagerConfiguration
     */
    public function getDependencyConfig(): array
    {
        return [
            'abstract_factories' => [
                FormAbstractServiceFactory::class,
            ],
            'aliases' => [
                FormElementManager::class => 'FormElementManager',
            ],
            'factories' => [
                'FormElementManager' => FormElementManagerFactory::class,
            ],
        ];
    }

    /**
     * Return jield-form helper configuration.
     *
     * Obsoletes View\HelperConfig.
     *
     * @return ServiceManagerConfiguration
     */
    public function getViewHelperConfig(): array
    {
        return [
            'aliases' => [
                'form' => View\Helper\Form::class,
                'Form' => View\Helper\Form::class,
                'formbutton' => View\Helper\FormButton::class,
                'form_button' => View\Helper\FormButton::class,
                'formButton' => View\Helper\FormButton::class,
                'FormButton' => View\Helper\FormButton::class,
                'formcheckbox' => View\Helper\FormCheckbox::class,
                'form_checkbox' => View\Helper\FormCheckbox::class,
                'formCheckbox' => View\Helper\FormCheckbox::class,
                'FormCheckbox' => View\Helper\FormCheckbox::class,
                'formcollection' => View\Helper\FormCollection::class,
                'form_collection' => View\Helper\FormCollection::class,
                'formCollection' => View\Helper\FormCollection::class,
                'FormCollection' => View\Helper\FormCollection::class,
                'formcolor' => View\Helper\FormColor::class,
                'form_color' => View\Helper\FormColor::class,
                'formColor' => View\Helper\FormColor::class,
                'FormColor' => View\Helper\FormColor::class,
                'formdate' => View\Helper\FormDate::class,
                'form_date' => View\Helper\FormDate::class,
                'formDate' => View\Helper\FormDate::class,
                'FormDate' => View\Helper\FormDate::class,
                'formdatetimelocal' => View\Helper\FormDateTimeLocal::class,
                'form_date_time_local' => View\Helper\FormDateTimeLocal::class,
                'formDateTimeLocal' => View\Helper\FormDateTimeLocal::class,
                'FormDateTimeLocal' => View\Helper\FormDateTimeLocal::class,
                'formdatetimeselect' => View\Helper\FormDateTimeSelect::class,
                'form_date_time_select' => View\Helper\FormDateTimeSelect::class,
                'formDateTimeSelect' => View\Helper\FormDateTimeSelect::class,
                'FormDateTimeSelect' => View\Helper\FormDateTimeSelect::class,
                'formdateselect' => View\Helper\FormDateSelect::class,
                'form_date_select' => View\Helper\FormDateSelect::class,
                'formDateSelect' => View\Helper\FormDateSelect::class,
                'FormDateSelect' => View\Helper\FormDateSelect::class,
                'form_element' => View\Helper\FormElement::class,
                'formelement' => View\Helper\FormElement::class,
                'formElement' => View\Helper\FormElement::class,
                'FormElement' => View\Helper\FormElement::class,
                'form_element_errors' => View\Helper\FormElementErrors::class,
                'formelementerrors' => View\Helper\FormElementErrors::class,
                'formElementErrors' => View\Helper\FormElementErrors::class,
                'FormElementErrors' => View\Helper\FormElementErrors::class,
                'form_email' => View\Helper\FormEmail::class,
                'formemail' => View\Helper\FormEmail::class,
                'formEmail' => View\Helper\FormEmail::class,
                'FormEmail' => View\Helper\FormEmail::class,
                'form_file' => View\Helper\FormFile::class,
                'formfile' => View\Helper\FormFile::class,
                'formFile' => View\Helper\FormFile::class,
                'FormFile' => View\Helper\FormFile::class,
                'formfileapcprogress' => View\Helper\File\FormFileApcProgress::class,
                'form_file_apc_progress' => View\Helper\File\FormFileApcProgress::class,
                'formFileApcProgress' => View\Helper\File\FormFileApcProgress::class,
                'FormFileApcProgress' => View\Helper\File\FormFileApcProgress::class,
                'formfilesessionprogress' => View\Helper\File\FormFileSessionProgress::class,
                'form_file_session_progress' => View\Helper\File\FormFileSessionProgress::class,
                'formFileSessionProgress' => View\Helper\File\FormFileSessionProgress::class,
                'FormFileSessionProgress' => View\Helper\File\FormFileSessionProgress::class,
                'formfileuploadprogress' => View\Helper\File\FormFileUploadProgress::class,
                'form_file_upload_progress' => View\Helper\File\FormFileUploadProgress::class,
                'formFileUploadProgress' => View\Helper\File\FormFileUploadProgress::class,
                'FormFileUploadProgress' => View\Helper\File\FormFileUploadProgress::class,
                'formhidden' => View\Helper\FormHidden::class,
                'form_hidden' => View\Helper\FormHidden::class,
                'formHidden' => View\Helper\FormHidden::class,
                'FormHidden' => View\Helper\FormHidden::class,
                'formimage' => View\Helper\FormImage::class,
                'form_image' => View\Helper\FormImage::class,
                'formImage' => View\Helper\FormImage::class,
                'FormImage' => View\Helper\FormImage::class,
                'forminput' => View\Helper\FormInput::class,
                'form_input' => View\Helper\FormInput::class,
                'formInput' => View\Helper\FormInput::class,
                'FormInput' => View\Helper\FormInput::class,
                'formlabel' => View\Helper\FormLabel::class,
                'form_label' => View\Helper\FormLabel::class,
                'formLabel' => View\Helper\FormLabel::class,
                'FormLabel' => View\Helper\FormLabel::class,
                'formmonth' => View\Helper\FormMonth::class,
                'form_month' => View\Helper\FormMonth::class,
                'formMonth' => View\Helper\FormMonth::class,
                'FormMonth' => View\Helper\FormMonth::class,
                'formmonthselect' => View\Helper\FormMonthSelect::class,
                'form_month_select' => View\Helper\FormMonthSelect::class,
                'formMonthSelect' => View\Helper\FormMonthSelect::class,
                'FormMonthSelect' => View\Helper\FormMonthSelect::class,
                'formmulticheckbox' => View\Helper\FormMultiCheckbox::class,
                'form_multi_checkbox' => View\Helper\FormMultiCheckbox::class,
                'formMultiCheckbox' => View\Helper\FormMultiCheckbox::class,
                'FormMultiCheckbox' => View\Helper\FormMultiCheckbox::class,
                'formnumber' => View\Helper\FormNumber::class,
                'form_number' => View\Helper\FormNumber::class,
                'formNumber' => View\Helper\FormNumber::class,
                'FormNumber' => View\Helper\FormNumber::class,
                'formpassword' => View\Helper\FormPassword::class,
                'form_password' => View\Helper\FormPassword::class,
                'formPassword' => View\Helper\FormPassword::class,
                'FormPassword' => View\Helper\FormPassword::class,
                'formradio' => View\Helper\FormRadio::class,
                'form_radio' => View\Helper\FormRadio::class,
                'formRadio' => View\Helper\FormRadio::class,
                'FormRadio' => View\Helper\FormRadio::class,
                'formrange' => View\Helper\FormRange::class,
                'form_range' => View\Helper\FormRange::class,
                'formRange' => View\Helper\FormRange::class,
                'FormRange' => View\Helper\FormRange::class,
                'formreset' => View\Helper\FormReset::class,
                'form_reset' => View\Helper\FormReset::class,
                'formReset' => View\Helper\FormReset::class,
                'FormReset' => View\Helper\FormReset::class,
                'formrow' => View\Helper\FormRow::class,
                'form_row' => View\Helper\FormRow::class,
                'formRow' => View\Helper\FormRow::class,
                'FormRow' => View\Helper\FormRow::class,
                'formsearch' => View\Helper\FormSearch::class,
                'form_search' => View\Helper\FormSearch::class,
                'formSearch' => View\Helper\FormSearch::class,
                'FormSearch' => View\Helper\FormSearch::class,
                'formselect' => View\Helper\FormSelect::class,
                'form_select' => View\Helper\FormSelect::class,
                'formSelect' => View\Helper\FormSelect::class,
                'FormSelect' => View\Helper\FormSelect::class,
                'formsubmit' => View\Helper\FormSubmit::class,
                'form_submit' => View\Helper\FormSubmit::class,
                'formSubmit' => View\Helper\FormSubmit::class,
                'FormSubmit' => View\Helper\FormSubmit::class,
                'formtel' => View\Helper\FormTel::class,
                'form_tel' => View\Helper\FormTel::class,
                'formTel' => View\Helper\FormTel::class,
                'FormTel' => View\Helper\FormTel::class,
                'formtext' => View\Helper\FormText::class,
                'form_text' => View\Helper\FormText::class,
                'formText' => View\Helper\FormText::class,
                'FormText' => View\Helper\FormText::class,
                'formtextarea' => View\Helper\FormTextarea::class,
                'form_text_area' => View\Helper\FormTextarea::class,
                'formTextarea' => View\Helper\FormTextarea::class,
                'formTextArea' => View\Helper\FormTextarea::class,
                'FormTextArea' => View\Helper\FormTextarea::class,
                'formtime' => View\Helper\FormTime::class,
                'form_time' => View\Helper\FormTime::class,
                'formTime' => View\Helper\FormTime::class,
                'FormTime' => View\Helper\FormTime::class,
                'formurl' => View\Helper\FormUrl::class,
                'form_url' => View\Helper\FormUrl::class,
                'formUrl' => View\Helper\FormUrl::class,
                'FormUrl' => View\Helper\FormUrl::class,
                'formweek' => View\Helper\FormWeek::class,
                'form_week' => View\Helper\FormWeek::class,
                'formWeek' => View\Helper\FormWeek::class,
                'FormWeek' => View\Helper\FormWeek::class,
            ],
            'factories' => [
                View\Helper\Form::class => InvokableFactory::class,
                View\Helper\FormButton::class => InvokableFactory::class,
                View\Helper\FormCheckbox::class => InvokableFactory::class,
                View\Helper\FormCollection::class => InvokableFactory::class,
                View\Helper\FormColor::class => InvokableFactory::class,
                View\Helper\FormDate::class => InvokableFactory::class,
                View\Helper\FormDateTimeLocal::class => InvokableFactory::class,
                View\Helper\FormDateTimeSelect::class => InvokableFactory::class,
                View\Helper\FormDateSelect::class => InvokableFactory::class,
                View\Helper\FormElement::class => FormElementFactory::class,
                View\Helper\FormElementErrors::class => FormElementErrorsFactory::class,
                View\Helper\FormEmail::class => InvokableFactory::class,
                View\Helper\FormFile::class => InvokableFactory::class,
                View\Helper\File\FormFileApcProgress::class => InvokableFactory::class,
                View\Helper\File\FormFileSessionProgress::class => InvokableFactory::class,
                View\Helper\File\FormFileUploadProgress::class => InvokableFactory::class,
                View\Helper\FormHidden::class => InvokableFactory::class,
                View\Helper\FormImage::class => InvokableFactory::class,
                View\Helper\FormInput::class => InvokableFactory::class,
                View\Helper\FormLabel::class => InvokableFactory::class,
                View\Helper\FormMonth::class => InvokableFactory::class,
                View\Helper\FormMonthSelect::class => InvokableFactory::class,
                View\Helper\FormMultiCheckbox::class => InvokableFactory::class,
                View\Helper\FormNumber::class => InvokableFactory::class,
                View\Helper\FormPassword::class => InvokableFactory::class,
                View\Helper\FormRadio::class => InvokableFactory::class,
                View\Helper\FormRange::class => InvokableFactory::class,
                View\Helper\FormReset::class => InvokableFactory::class,
                View\Helper\FormRow::class => InvokableFactory::class,
                View\Helper\FormSearch::class => InvokableFactory::class,
                View\Helper\FormSelect::class => InvokableFactory::class,
                View\Helper\FormSubmit::class => InvokableFactory::class,
                View\Helper\FormTel::class => InvokableFactory::class,
                View\Helper\FormText::class => InvokableFactory::class,
                View\Helper\FormTextarea::class => InvokableFactory::class,
                View\Helper\FormTime::class => InvokableFactory::class,
                View\Helper\FormUrl::class => InvokableFactory::class,
                View\Helper\FormWeek::class => InvokableFactory::class,
            ],
        ];
    }
}
