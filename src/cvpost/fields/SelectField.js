import React from "react";
import {updateLanguageField} from "../../features/cvpost/cvpostSlicer";
import {store} from "../../store/mainStore";

function SelectField(props) {
  return (
    <select onChange={(e) => updateSelectField(e.target.value, props)}>
      <option></option>
      {props.field.options.map(fieldOption => {
        return <option value={fieldOption} selected={props.defaultVal[props.field.id] === fieldOption}>{fieldOption}</option>
      })}
    </select>
  )
}

function updateSelectField(newVal, props) {
  const _innner_id = props.defaultVal._inner_id
  store.dispatch(updateLanguageField({field: props.field, newVal: newVal, _inner_id: _innner_id}))
  console.log(props.field)
}

export default SelectField