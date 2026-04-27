{**
 * plugins/generic/thoth/templates/publicationFormatAccessibilityFields.tpl
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Thoth accessibility metadata fields for publication formats
 *}

{fbvFormSection for="thothAccessibility" title="plugins.generic.thoth.publicationFormat.accessibility"}
	{fbvElement type="select" id="accessibilityStandard" label="plugins.generic.thoth.publicationFormat.accessibilityStandard" from=$thothAccessibilityStandardOptions selected=$accessibilityStandard size=$fbvStyles.size.MEDIUM inline=true}
	{fbvElement type="select" id="accessibilityAdditionalStandard" label="plugins.generic.thoth.publicationFormat.accessibilityAdditionalStandard" from=$thothAccessibilityStandardOptions selected=$accessibilityAdditionalStandard size=$fbvStyles.size.MEDIUM inline=true}
	{fbvElement type="select" id="accessibilityException" label="plugins.generic.thoth.publicationFormat.accessibilityException" from=$thothAccessibilityExceptionOptions selected=$accessibilityException size=$fbvStyles.size.MEDIUM inline=true}
	{fbvElement type="text" id="accessibilityReportUrl" label="plugins.generic.thoth.publicationFormat.accessibilityReportUrl" value=$accessibilityReportUrl size=$fbvStyles.size.MEDIUM inline=true}
{/fbvFormSection}
