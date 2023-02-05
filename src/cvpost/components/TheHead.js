import {useEffect} from "react";
import {store} from "../../store/mainStore";
import {setField, setNonce, setNonceName, setInitialRepeatableField} from "../../features/cvpost/cvpostSlicer";

function TheHead(props) {
  useEffect(() => {
    {props.fields.map(field => {
      if (props.meta[field.id]) {
        let value = props.meta[field.id]?.[0] ?? ""
        if (field.type === 'repeatable') {
          store.dispatch(setInitialRepeatableField({field: field, value: JSON.parse(props.meta[field.id])}))
        } else {
          store.dispatch(setField({field: field, value: value}))
        }
      }
    })}
    store.dispatch(setNonce(props.nonce))
    store.dispatch(setNonceName(props.nonce_name))
  }, [])

  store.subscribe(() => {
    console.log("Store updated!")
    console.log(store.getState().cvpost)
  })

  return ""
}

export default TheHead;