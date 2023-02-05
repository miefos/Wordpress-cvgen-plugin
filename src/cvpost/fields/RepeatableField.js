import React from "react";
import Field from "./Field";
import {store} from "../../store/mainStore";
import {addRepeatableFieldRow, deleteLanguageField} from "../../features/cvpost/cvpostSlicer";
import {useDispatch, useSelector} from "react-redux";

function RepeatableField(props) {
  const field = props.field
  const lang_repeatable_field = useSelector((state) => state.cvpost[field.id])

  return (
    <div className="repeatable-field">
      <div>
        <label>{field.label}</label>
      </div>
      {lang_repeatable_field.map(val => (
      <div>
        {field.inner_fields.map(inner_field => (
            <Field {...props} field={inner_field} defaultVal={val}/>
        ))}
        <span className="clickableElem" onClick={() => removeRepeatableFieldRowFunction({...props, val: val})}>-</span>
      </div>
      ))}
      <span className="clickableElem" onClick={() => addRepeatableFieldRowFunction(props)}>+ Add </span>
    </div>
  )
}

function removeRepeatableFieldRowFunction(props) {
  store.dispatch(deleteLanguageField(props.val._inner_id))
}

function addRepeatableFieldRowFunction(props) {
  const val = {}

  const inner_fields = props.field.inner_fields
  inner_fields.forEach(inner_field => {
    val[inner_field.id] = ""
  })

  store.dispatch(addRepeatableFieldRow(
    {field: props.field, value: val}
  ))
}

export default RepeatableField
