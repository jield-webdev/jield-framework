<?php

declare(strict_types=1);

namespace Jield\Form\View;

use IntlDateFormatter;
use Jield\Form\ElementInterface;
use Jield\Form\FormInterface;
use Jield\Form\View\Helper\Form;
use Jield\Form\View\Helper\FormButton;
use Jield\Form\View\Helper\FormCheckbox;
use Jield\Form\View\Helper\FormCollection;
use Jield\Form\View\Helper\FormColor;
use Jield\Form\View\Helper\FormDate;
use Jield\Form\View\Helper\FormDateSelect;
use Jield\Form\View\Helper\FormDateTimeLocal;
use Jield\Form\View\Helper\FormDateTimeSelect;
use Jield\Form\View\Helper\FormElement;
use Jield\Form\View\Helper\FormElementErrors;
use Jield\Form\View\Helper\FormEmail;
use Jield\Form\View\Helper\FormFile;
use Jield\Form\View\Helper\FormHidden;
use Jield\Form\View\Helper\FormImage;
use Jield\Form\View\Helper\FormInput;
use Jield\Form\View\Helper\FormLabel;
use Jield\Form\View\Helper\FormMonth;
use Jield\Form\View\Helper\FormMonthSelect;
use Jield\Form\View\Helper\FormMultiCheckbox;
use Jield\Form\View\Helper\FormNumber;
use Jield\Form\View\Helper\FormPassword;
use Jield\Form\View\Helper\FormRadio;
use Jield\Form\View\Helper\FormRange;
use Jield\Form\View\Helper\FormReset;
use Jield\Form\View\Helper\FormRow;
use Jield\Form\View\Helper\FormSearch;
use Jield\Form\View\Helper\FormSelect;
use Jield\Form\View\Helper\FormSubmit;
use Jield\Form\View\Helper\FormTel;
use Jield\Form\View\Helper\FormText;
use Jield\Form\View\Helper\FormTextarea;
use Jield\Form\View\Helper\FormTime;
use Jield\Form\View\Helper\FormUrl;
use Jield\Form\View\Helper\FormWeek;

// @codingStandardsIgnoreStart

/**
 * Helper trait for auto-completion of code in modern IDEs.
 *
 * The trait provides convenience methods for view helpers,
 * defined by the jield-form component. It is designed to be used
 * for type-hinting $this variable inside jield-view templates via doc blocks.
 *
 * The base class is PhpRenderer, followed by the helper trait from
 * the jield-form component. However, multiple helper traits from different
 * Jield components can be chained afterwards.
 *
 * @example @var \Jield\Mvc\View\Renderer\PhpRenderer|\Jield\Form\View\HelperTrait $this
 *
 * @method string|Form form(FormInterface|null $form = null)
 * @method string|FormButton formButton(ElementInterface|null $element = null, string|null $buttonContent = null)
 * @method string|FormCheckbox formCheckbox(ElementInterface|null $element = null)
 * @method string|FormCollection formCollection(ElementInterface|null $element = null, bool $wrap = true)
 * @method string|FormColor formColor(ElementInterface|null $element = null)
 * @method string|FormDate formDate(ElementInterface|null $element = null)
 * @method string|FormDateTimeLocal formDateTimeLocal(ElementInterface|null $element = null)
 * @method string|FormDateTimeSelect formDateTimeSelect(ElementInterface|null $element = null, int $dateType = IntlDateFormatter::LONG, int|null|string $timeType = IntlDateFormatter::LONG, string|null $locale = null)
 * @method string|FormDateSelect formDateSelect(ElementInterface $element = null, $dateType = IntlDateFormatter::LONG, $locale = null)
 * @method string|FormElement formElement(ElementInterface|null $element = null)
 * @method string|FormElementErrors formElementErrors(ElementInterface|null $element = null, array $attributes = [])
 * @method string|FormEmail formEmail(ElementInterface|null $element = null)
 * @method string|FormFile formFile(ElementInterface|null $element = null)
 * @method string formFileApcProgress(ElementInterface|null $element = null)
 * @method string formFileSessionProgress(ElementInterface|null $element = null)
 * @method string formFileUploadProgress(ElementInterface|null $element = null)
 * @method string|FormHidden formHidden(ElementInterface|null $element = null)
 * @method string|FormImage formImage(ElementInterface|null $element = null)
 * @method string|FormInput formInput(ElementInterface|null $element = null)
 * @method string|FormLabel formLabel(ElementInterface|null $element = null, string|null $labelContent = null, string|null $position = null)
 * @method string|FormMonth formMonth(ElementInterface|null $element = null)
 * @method string|FormMonthSelect formMonthSelect(ElementInterface|null $element = null, int $dateType = IntlDateFormatter::LONG, string|null $locale = null)
 * @method string|FormMultiCheckbox formMultiCheckbox(ElementInterface|null $element = null, string|null $labelPosition = null)
 * @method string|FormNumber formNumber(ElementInterface|null $element = null)
 * @method string|FormPassword formPassword(ElementInterface|null $element = null)
 * @method string|FormRadio formRadio(ElementInterface|null $element = null, string|null $labelPosition = null)
 * @method string|FormRange formRange(ElementInterface|null $element = null)
 * @method string|FormReset formReset(ElementInterface|null $element = null)
 * @method string|FormRow formRow(ElementInterface|null $element = null, string|null $labelPosition = null, bool|null $renderErrors = null, string|null $partial = null)
 * @method string|FormSearch formSearch(ElementInterface|null $element = null)
 * @method string|FormSelect formSelect(ElementInterface|null $element = null)
 * @method string|FormSubmit formSubmit(ElementInterface|null $element = null)
 * @method string|FormTel formTel(ElementInterface|null $element = null)
 * @method string|FormText formText(ElementInterface|null $element = null)
 * @method string|FormTextarea formTextarea(ElementInterface|null $element = null)
 * @method string|FormTime formTime(ElementInterface|null $element = null)
 * @method string|FormUrl formUrl(ElementInterface|null $element = null)
 * @method string|FormWeek formWeek(ElementInterface|null $element = null)
 */
trait HelperTrait
{
}

// @codingStandardsIgnoreEnd
