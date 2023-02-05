import {useSelector} from "react-redux";
import React from "react";

function Message() {
  const response = useSelector((state) => state.cvpost.apiResponse)
  if (!response) return;

  return (
    <div className={`${response.status === 'ok' ? "ok-status-info" : "fail-status-info"}`}>
      { response.msg }
    </div>
  )
}

export default Message