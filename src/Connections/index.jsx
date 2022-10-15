import apiFetch from "@wordpress/api-fetch";

const { Fragment, useState, useEffect } = wp.element;

const Connections = () => {
  const [connections, setConnections] = useState([]);

  useEffect(async () => {
    const connectionResponse = await apiFetch({
      path: "/smolblog/v2/my/connections",
    });

    setConnections(connectionResponse.connections);
  }, []);

  return (
    <Fragment>
      <table className="widefat striped fixed">
        <colgroup>
          <col span="1" style={{ width: "50px" }} />
          <col span="1" />
          <col span="1" />
        </colgroup>
        <thead>
          <tr>
            <th colSpan={2}>Connection</th>
            <th>Channels</th>
          </tr>
        </thead>

        <tbody>
          {connections?.map((conn) => (
            <tr>
              <td>{conn.provider}</td>
              <td>{conn.displayName}</td>
              <td>
                <ul>
                  {conn.channels.map((channel) => (
                    <li>{channel.displayName}</li>
                  ))}
                </ul>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <button
        className="button"
        onClick={() =>
          apiFetch({ path: "smolblog/v2/connect/init/twitter" })
            .then((result) => (window.location.href = result.authUrl))
            .catch((e) => console.error(e))
        }
      >
        Connect to Twitter
      </button>
    </Fragment>
  );
};

export default Connections;
