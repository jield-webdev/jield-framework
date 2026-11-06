<?php

// Model the named metadata keys separately from the input entries. The vendor
// intersections otherwise require `type` to be both a string and an input.
namespace Laminas\InputFilter;

/**
 * @psalm-import-type FilterSpecification from \Laminas\Filter\FilterChain
 * @psalm-import-type ValidatorSpecification from \Laminas\Validator\ValidatorChain
 * @psalm-type InputSpecification = array{
 *     type?: string|class-string<InputFilterInterface>, name?: array-key,
 *     required?: bool, allow_empty?: bool, continue_if_empty?: bool,
 *     error_message?: string|null, fallback_value?: mixed, break_on_failure?: bool,
 *     filters?: \Laminas\Filter\FilterChain|iterable<array-key, FilterSpecification|callable|\Laminas\Filter\FilterInterface>,
 *     validators?: \Laminas\Validator\ValidatorChain|iterable<array-key, ValidatorSpecification|\Laminas\Validator\ValidatorInterface>,
 *     ...
 * }
 * @psalm-type InputFilterSpecification = array{
 *     type?: class-string<InputFilterInterface>|string,
 *     ...<array-key, InputSpecification|InputFilterInterface|InputInterface>
 * }
 * @psalm-type CollectionSpecification = array{
 *     type?: class-string<InputFilterInterface>|string,
 *     input_filter?: InputFilterSpecification|InputFilterInterface,
 *     count?: int, required?: bool, required_message?: string,
 *     ...<array-key, InputSpecification>
 * }
 */
interface InputFilterInterface
{
}
