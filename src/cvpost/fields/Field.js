import RepeatableField from "./RepeatableField";
import React from "react";
import TextField from "./TextField";
import SelectField from "./SelectField";

function Field(props) {
  const field = props.field

  switch (field.type) {
    case 'tel':
    case 'text': {
      return <TextField {...props}/>
    }
    case 'repeatable': {
      return <RepeatableField {...props}/>
    }
    case 'select': {
      return <SelectField {...props}/>
    }
    default: {
      return "Unrecognized field!"
    }
  }
}

export default Field