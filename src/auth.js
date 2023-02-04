import React, { useState, useEffect } from 'react'
import axios from 'axios'
import ReactDOM from 'react-dom'
import { createSlice, configureStore } from '@reduxjs/toolkit'
import { TextControl, Flex, FlexBlock, FlexItem, Button, Icon, PanelBody, PanelRow, ColorPicker } from "@wordpress/components"
import "./auth.scss"
import { store } from './store/authStore'
import {Provider, useDispatch, useSelector} from 'react-redux'
import authSlicer, {setApiResponse, setEmail, setNonce, setNonceName, setOTP} from "./features/auth/authSlicer";

/**
 *  React components
 *
 */
document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("auth_form")
  const data = JSON.parse(root.querySelector("pre").innerHTML)

  ReactDOM.render(
    <Provider store={store}>
      <Message {...data} />
      <AuthForm {...data}/>
    </Provider>,
    root
  )
})

function Message(props) {
  const response = useSelector((state) => state.auth.apiResponse)

  return (
    <div className={`${response.status === 'ok' ? "ok-status-info" : "fail-status-info"}`}>
      { response.msg }
    </div>
  )
}

function AuthForm(props) {
  const em = useSelector((state) => state.auth.apiResponse)
  const dispatch = useDispatch()
  console.log("auth")
  console.log(em)

  useEffect(() => {
    dispatch(setNonce(props.nonce))
    dispatch(setEmail(props.email))
    dispatch(setNonceName(props.nonce_name))
  }, [])

  return (
    <div>
      <EmailField {...props} />
      <div>
        {
          em.status === 'ok'
            ?
          <div>
            <OTPField {...props} />
            <input type="submit" onClick={authorizeOTP} value={props.submit_attempt_email_otp} />
          </div>
          :
          <input type="submit" onClick={authorizeEmail} value={props.submit_email} />
        }
      </div>
    </div>
  )
}

function authorizeOTP() {
  const email = store.getState().auth.email
  const nonceName = store.getState().auth.nonceName
  const nonce = store.getState().auth.nonce
  const otp = store.getState().auth.otp
  const data = {
    email: email,
    [nonceName]: nonce,
    otp: otp
  }

  axios.post('/wp-json/cvgen/auth/attempt_otp', data)
    .then(function (response) {
      store.dispatch(setApiResponse(response.data))
      console.log(response);
    })
    .catch(function (error) {
      console.log(error);
    });
}

function authorizeEmail() {
  const email = store.getState().auth.email
  const nonceName = store.getState().auth.nonceName
  const nonce = store.getState().auth.nonce
  const data = {
    email: email,
    [nonceName]: nonce
  }

  axios.post('/wp-json/cvgen/auth/send_otp', data)
    .then(function (response) {
      store.dispatch(setApiResponse(response.data))
      console.log(response);
    })
    .catch(function (error) {
      console.log(error);
    });
}

function OTPField(props) {
  const dispatch = useDispatch()

  return (
    <div>
      <div>
        <label htmlFor="email">{props.otp_label}</label>
      </div>
      <div>
        <input type="number" name="otp" id="otp" onChange={(e) => dispatch(setOTP(e.target.value))} />
      </div>
    </div>
  )
}

function EmailField(props) {
  const defaultEmail = props.email
  const email = useSelector((state) => state.auth.email)
  const dispatch = useDispatch()

  return (
    <div>
      <div>
        <label htmlFor="email">{props.email_label}</label>
      </div>
      <div>
        <input type="email" name="email" id="email" defaultValue={defaultEmail} onChange={(e) => dispatch(setEmail(e.target.value))} />
      </div>
    </div>
  )
}