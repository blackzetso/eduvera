/**
 * Port of App\Support\FormBuilder\FormLogicEffects
 */
export class FormLogicEffects {
  constructor({
    visibleByLogic = {},
    hiddenByLogic = {},
    requiredByLogic = {},
    skippedSections = {},
  } = {}) {
    this.visibleByLogic = visibleByLogic
    this.hiddenByLogic = hiddenByLogic
    this.requiredByLogic = requiredByLogic
    this.skippedSections = skippedSections
  }

  isSectionSkipped(sectionId) {
    if (!sectionId) {
      return false
    }

    return Boolean(this.skippedSections[sectionId])
  }

  isFieldVisibleByLogic(fieldKey) {
    if (this.hiddenByLogic[fieldKey]) {
      return false
    }

    if (this.visibleByLogic[fieldKey]) {
      return true
    }

    return !Object.prototype.hasOwnProperty.call(this.hiddenByLogic, fieldKey)
      && !Object.prototype.hasOwnProperty.call(this.visibleByLogic, fieldKey)
  }

  isLogicRequired(fieldKey) {
    return Boolean(this.requiredByLogic[fieldKey])
  }

  isFieldEffective(field) {
    if (field.hidden) {
      return false
    }

    if (this.isSectionSkipped(field.sectionId)) {
      return false
    }

    return this.isFieldVisibleByLogic(field.key)
  }

  isFieldRequired(field) {
    if (!this.isFieldEffective(field)) {
      return false
    }

    return Boolean(field.required) || this.isLogicRequired(field.key)
  }

  signature() {
    return JSON.stringify({
      visible_by_logic: this.visibleByLogic,
      hidden_by_logic: this.hiddenByLogic,
      required_by_logic: this.requiredByLogic,
      skipped_sections: this.skippedSections,
    })
  }

  equals(other) {
    return this.signature() === other.signature()
  }
}
