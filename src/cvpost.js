import ReactDOM from "react-dom";
import React, {useEffect} from "react";
import axios from 'axios'
import { store } from './store/mainStore'
import {Provider, useDispatch, useSelector} from 'react-redux'
import {setApiResponse} from "./features/cvpost/cvpostSlicer";
import TheHead from "./cvpost/components/TheHead";
import Message from "./cvpost/components/Message";
import Field from "./cvpost/fields/Field";

document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("cvpost_form")
  const data = JSON.parse(root.querySelector("pre").innerHTML)

  console.log(data)

  ReactDOM.render(
    <Provider store={store}>
      <TheHead {...data}/>
      <Message />
      {data.fields.map(field => {
        return <Field {...data} field={field}/>
      })}
      <div>
        <input onClick={() => submitForm(data)} type="submit" />
      </div>
    </Provider>,
    root
  )
})

function submitForm(props) {
  const state = store.getState().cvpost

  const data = {
    [state.nonceName]: state.nonce,
  };

  {props.fields.forEach(field => {
    let val = state[field.id]
    if (field.type === "repeatable") {
      val = JSON.stringify(state[field.id])
    }

    data[field.id] = val ?? null
  })}

  axios.post('/wp-json/cvgen/cvpost/update', data)
    .then(function (response) {
      store.dispatch(setApiResponse(response.data))
      // if (response.data.status === 'ok') {
      //   location.reload()
    // }
    })
    .catch(function (error) {
      console.log("Error authorizing OTP");
    });
}


