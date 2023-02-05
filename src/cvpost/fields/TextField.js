import React from "react";
import {store} from "../../store/mainStore";
import {setField} from "../../features/cvpost/cvpostSlicer";

function TextField(props) {
  const field = props.field

  return (
    <div>
      <label>{field.label}</label>
      <input type="text" id={field.id} defaultValue={props.meta[field.id]} onChange={(e) => updateField(e.target.value, field)} />
    </div>
  )
}


function updateField(newVal, field) {
  store.dispatch(setField({field: field, value: newVal}))
}

export default TextField