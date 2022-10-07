import apiFetch from "@wordpress/api-fetch";

const Connections = () => {
  return (
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
  );
};

export default Connections;
